import copy
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
(ROOT / '.work').mkdir(exist_ok=True)
spec = importlib.util.spec_from_file_location('drafts', ROOT / 'tools/create-draft-release.py')
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
SHA = 'a' * 40
TAG = 'v1.0.0'

def draft(id=7, published=False):
 return {'id': id, 'tag_name': TAG, 'draft': not published, 'published_at': 'now' if published else None,
         'upload_url': 'https://uploads.github.com/repos/example/test/releases/7/assets{?name,label}',
         'html_url': 'https://github.com/example/test/releases/7'}

class FakeAPI:
 def __init__(self, releases=None, bytag=None, annotated=False):
  self.releases = releases or []
  self.bytag = bytag
  self.calls = []
  self.annotated = annotated
  self.assets = []
  self.publish_on_refresh = False
  self.create_race = False
  self.pages = None
 def call(self, method, path, payload=None, binary=None):
  self.calls.append((method, path, payload))
  if path.startswith('/git/ref/'):
   return {'object': {'type': 'tag' if self.annotated else 'commit', 'sha': 'b'*40 if self.annotated else SHA}}
  if path.startswith('/git/tags/'): return {'object': {'type': 'commit', 'sha': SHA}}
  if method == 'GET' and path.startswith('/releases?'):
   page = int(path.split('page=')[-1])
   return copy.deepcopy(self.pages.get(page, []) if self.pages else self.releases)
  if path.startswith('/releases/tags/'):
   if self.bytag: return copy.deepcopy(self.bytag)
   raise m.APIError(404)
  if method == 'POST' and path == '/releases':
   self.releases = [draft()]
   if self.create_race: raise m.APIError(422)
   return draft()
  if method == 'GET' and '/assets?' in path: return copy.deepcopy(self.assets)
  if method == 'GET' and path.startswith('/releases/'):
   return draft(published=self.publish_on_refresh)
  if method == 'DELETE': return None
  if method == 'POST' and path.startswith('https://uploads.github.com/'):
   return {'size': len(binary), 'state': 'uploaded'}
  raise AssertionError((method,path))
 def mutations(self): return [c for c in self.calls if c[0] != 'GET']

class DraftTests(unittest.TestCase):
 def test_first_release_creation_uses_draft_and_existing_commit(self):
  api = FakeAPI(); result = m.DraftRelease(api).ensure(TAG, SHA, 'notes')
  self.assertEqual(7, result['id'])
  payload = api.mutations()[0][2]
  self.assertTrue(payload['draft']); self.assertEqual(SHA, payload['target_commitish'])
  self.assertEqual(1,len(api.mutations()))
 def test_same_release_id_is_deduplicated_and_app_permissions_not_assumed(self):
  api = FakeAPI([draft(), draft()], draft())
  m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual([],api.mutations())
 def test_distinct_drafts_abort_without_mutation(self):
  api = FakeAPI([draft(7),draft(8)])
  with self.assertRaises(m.ReleaseError): m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual([],api.mutations())
 def test_published_release_is_never_changed(self):
  api = FakeAPI([draft()], draft(published=True))
  with self.assertRaises(m.ReleaseError): m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual([],api.mutations())
 def test_wrong_remote_commit_aborts(self):
  api = FakeAPI()
  with self.assertRaises(m.ReleaseError): m.DraftRelease(api).ensure(TAG,'c'*40,'notes')
  self.assertEqual([],api.mutations())
 def test_annotated_tag_is_resolved(self):
  api = FakeAPI([draft()],annotated=True)
  m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual([],api.mutations())
 def test_create_422_race_resumes_without_second_post(self):
  api = FakeAPI(); api.create_race = True
  m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual(1,len(api.mutations()))
 def test_publication_during_run_aborts_before_asset_mutation(self):
  api = FakeAPI([draft()]); api.publish_on_refresh = True
  with self.assertRaises(m.ReleaseError): m.DraftRelease(api).upload(draft(),TAG, [Path('unused')])
  self.assertEqual([],api.mutations())
 def test_replace_stale_asset_only_on_draft(self):
  api = FakeAPI([draft()]); api.assets = [{'id':9,'name':'asset.zip','size':1}]
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   path = Path(temporary)/'asset.zip'; path.write_bytes(b'test')
   m.DraftRelease(api).upload(draft(),TAG,[path])
  self.assertEqual(['DELETE','POST'],[c[0] for c in api.mutations()])
  for index, call in enumerate(api.calls):
   if call[0] != 'GET': self.assertEqual(('GET','/releases/7'),api.calls[index-1][:2])
 def test_matching_asset_digest_is_reused(self):
  api = FakeAPI([draft()])
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   path=Path(temporary)/'asset.zip'; path.write_bytes(b'test')
   api.assets=[{'id':9,'name':path.name,'size':4,'digest':'sha256:'+m.hashlib.sha256(b'test').hexdigest()}]
   m.DraftRelease(api).upload(draft(),TAG,[path])
  self.assertEqual([],api.mutations())
 def test_untrusted_upload_host_is_rejected(self):
  api = m.API('example/test','fake-token')
  with self.assertRaises(m.ReleaseError): api.call('POST','https://example.invalid/upload',binary=b'test')

 def test_paginated_results_and_tag_lookup_deduplicate_same_id(self):
  api = FakeAPI(bytag=draft())
  unrelated = [dict(draft(100+i),tag_name='v0.9.'+str(i)) for i in range(99)]
  api.pages = {1: unrelated+[draft()], 2: [draft()]}
  m.DraftRelease(api).ensure(TAG,SHA,'notes')
  self.assertEqual([],api.mutations())
  self.assertTrue(any('page=2' in path for method,path,payload in api.calls))
 def test_unvalidated_local_manifest_is_refused(self):
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   directory = Path(temporary)
   (directory/'release-manifest.json').write_text(m.json.dumps({'tag':TAG,'source_commit':SHA,'clean_checkout':False}))
   with self.assertRaises(m.ReleaseError): m.inputs(directory,TAG,SHA)
