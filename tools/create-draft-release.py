#!/usr/bin/env python3
"""Create/resume a draft for an EXISTING verified tag via GitHub REST, never publish."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import urllib.error
import urllib.parse
import urllib.request

class ReleaseError(RuntimeError):
 pass

class APIError(ReleaseError):
 def __init__(self, status):
  self.status = status
  super().__init__('GitHub API request failed (HTTP %s); credentials and response body omitted.' % status)

class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self, req, fp, code, msg, headers, newurl):
  raise ReleaseError('API redirect refused; authorization was not forwarded.')

class API:
 def __init__(self, repository, token):
  if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository):
   raise ReleaseError('Expected OWNER/REPOSITORY')
  self.base = 'https://api.github.com/repos/' + repository
  self.token = token
  self.opener = urllib.request.build_opener(NoRedirect())
 def call(self, method, path, payload=None, binary=None):
  url = path if path.startswith('https://') else self.base + path
  parsed = urllib.parse.urlparse(url)
  if parsed.scheme != 'https' or parsed.hostname not in ('api.github.com', 'uploads.github.com'):
   raise ReleaseError('Untrusted API/upload host')
  headers = {'Authorization': 'Bearer ' + self.token, 'Accept': 'application/vnd.github+json',
             'X-GitHub-Api-Version': '2026-03-10', 'User-Agent': 'QuixDevs-Tickera-release'}
  data = None
  if payload is not None:
   data = json.dumps(payload).encode()
   headers['Content-Type'] = 'application/json'
  if binary is not None:
   data = binary
   headers['Content-Type'] = 'application/octet-stream'
  request = urllib.request.Request(url, data=data, headers=headers, method=method)
  try:
   with self.opener.open(request, timeout=90) as response:
    raw = response.read()
    return json.loads(raw) if raw else None
  except urllib.error.HTTPError as error:
   raise APIError(error.code) from None
  except (urllib.error.URLError, TimeoutError, OSError):
   raise ReleaseError('GitHub transport failed; inspect/resume the draft before retrying.') from None

class DraftRelease:
 def __init__(self, api):
  self.api = api
 def verify_tag(self, tag, commit):
  ref = self.api.call('GET', '/git/ref/tags/' + urllib.parse.quote(tag, safe=''))['object']
  for _ in range(10):
   if ref['type'] == 'commit':
    if ref['sha'] != commit: raise ReleaseError('Remote tag commit differs from validated package')
    return
   if ref['type'] != 'tag': raise ReleaseError('Tag does not resolve to a commit')
   ref = self.api.call('GET', '/git/tags/' + ref['sha'])['object']
  raise ReleaseError('Too many annotated tag levels')
 def lookup(self, tag):
  found = {}
  page = 1
  while True:
   releases = self.api.call('GET', '/releases?per_page=100&page=' + str(page))
   for release in releases:
    if release['tag_name'] == tag: found[release['id']] = release
   if len(releases) < 100: break
   page += 1
   if page > 10000: raise ReleaseError('Release pagination limit exceeded')
  try:
   release = self.api.call('GET', '/releases/tags/' + urllib.parse.quote(tag, safe=''))
   if release['tag_name'] == tag:
    previous = found.get(release['id'])
    if previous and not previous['draft']: release = previous
    found[release['id']] = release
  except APIError as error:
   if error.status != 404: raise
  if any(not release['draft'] or release.get('published_at') for release in found.values()):
   raise ReleaseError('Published release exists; all mutations refused')
  if len(found) > 1: raise ReleaseError('Distinct duplicate drafts require manual review; no mutations made')
  return next(iter(found.values()), None)
 def mutable(self, release_id, tag):
  release = self.api.call('GET', '/releases/' + str(release_id))
  if not release['draft'] or release.get('published_at') or release['tag_name'] != tag:
   raise ReleaseError('Release changed or was published; mutation refused')
  return release
 def ensure(self, tag, commit, notes):
  self.verify_tag(tag, commit)
  release = self.lookup(tag)
  if not release:
   try:
    release = self.api.call('POST', '/releases', {'tag_name': tag, 'target_commitish': commit,
        'name': 'QuixDevs Tickera REST API ' + tag, 'body': notes,
        'draft': True, 'prerelease': False, 'generate_release_notes': False})
   except ReleaseError:
    # A 422 or unknown transport outcome may mean another request created it.
    # Discover before proceeding; never retry POST blindly.
    release = self.lookup(tag)
    if not release: raise
  return self.mutable(release['id'], tag)
 def upload(self, release, tag, files):
  release_id = release['id']
  for path in files:
   release = self.mutable(release_id, tag)
   assets = []
   page = 1
   while True:
    chunk = self.api.call('GET', '/releases/%s/assets?per_page=100&page=%s' % (release_id, page))
    assets.extend(chunk)
    if len(chunk) < 100: break
    page += 1
   matches = [asset for asset in assets if asset['name'] == path.name]
   checksum = 'sha256:' + hashlib.sha256(path.read_bytes()).hexdigest()
   if len(matches) == 1 and matches[0].get('digest') == checksum and matches[0]['size'] == path.stat().st_size:
    continue
   for asset in matches:
    self.mutable(release_id, tag)
    self.api.call('DELETE', '/releases/assets/' + str(asset['id']))
   release = self.mutable(release_id, tag)
   url = release['upload_url'].split('{', 1)[0] + '?name=' + urllib.parse.quote(path.name, safe='')
   uploaded = self.api.call('POST', url, binary=path.read_bytes())
   if uploaded.get('size') != path.stat().st_size or uploaded.get('state') != 'uploaded':
    raise ReleaseError('Incomplete asset upload; rerun to resume the draft')
   if uploaded.get('digest') and uploaded['digest'] != checksum:
    raise ReleaseError('Uploaded asset digest mismatch')
  return self.mutable(release_id, tag)

def inputs(directory, tag, commit):
 manifest_path = directory / 'release-manifest.json'
 manifest = json.loads(manifest_path.read_text())
 if manifest['tag'] != tag or manifest['source_commit'] != commit or not manifest['clean_checkout']:
  raise ReleaseError('Manifest must identify the validated clean tag checkout')
 if tag != 'v' + manifest['version']: raise ReleaseError('Tag/version mismatch')
 archive = directory / 'quixdevs-tickera-rest-api.zip'
 checksum = directory / (archive.name + '.sha256')
 actual = hashlib.sha256(archive.read_bytes()).hexdigest()
 if actual != manifest['sha256'] or archive.stat().st_size != manifest['bytes']:
  raise ReleaseError('Archive/manifest mismatch')
 if checksum.read_text() != actual + '  ' + archive.name + '\n':
  raise ReleaseError('Checksum mismatch')
 return [archive, checksum, manifest_path]

if __name__ == '__main__':
 parser = argparse.ArgumentParser(description=__doc__)
 parser.add_argument('--repository', default=os.environ.get('GITHUB_REPOSITORY'))
 parser.add_argument('--tag', required=True)
 parser.add_argument('--commit', required=True)
 parser.add_argument('--dist', type=Path, required=True)
 parser.add_argument('--notes', type=Path, required=True)
 args = parser.parse_args()
 try:
  token = os.environ.get('RELEASE_TOKEN') or os.environ.get('GITHUB_TOKEN')
  if not token or not args.repository: raise ReleaseError('Repository and installation/access token required')
  if not re.fullmatch(r'v[0-9]+\.[0-9]+\.[0-9]+', args.tag) or not re.fullmatch(r'[0-9a-f]{40}', args.commit):
   raise ReleaseError('Expected stable version tag and full commit SHA')
  files = inputs(args.dist, args.tag, args.commit)
  release = DraftRelease(API(args.repository, token))
  draft = release.ensure(args.tag, args.commit, args.notes.read_text())
  result = release.upload(draft, args.tag, files)
  print('Draft ready for manual review: ' + result['html_url'])
 except (ReleaseError, KeyError, ValueError) as error:
  raise SystemExit(str(error)) from None
