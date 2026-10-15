#!/usr/bin/env python3
"""Reproducible allowlisted release; no Composer/runtime downloads required."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile
SLUG = 'quixdevs-tickera-rest-api'
VERSION = '1.0.0'
FILES = [SLUG + '.php', 'LICENSE', 'readme.txt', 'docs/rest-api.md'] + ['includes/' + name + '.php' for name in ('Plugin', 'Dependencies', 'Authorization/Ownership_Policy', 'Data/Tickera_Repository', 'Rest/Events_Controller', 'Rest/Tickets_Controller', 'Rest/Resource_Controller', 'Services/Resource_Service', 'Support/Date_Time', 'Support/Validator', 'Support/Write_Lock')]

def digest(data):
    return hashlib.sha256(data).hexdigest()

def git(source, *args):
    return subprocess.check_output(['git', '-C', str(source), *args], text=True, stderr=subprocess.DEVNULL).strip()

def validate_versions(source):
    header = (source / (SLUG + '.php')).read_text()
    if not re.search('\\* Version: ' + re.escape(VERSION) + '\\s*\\n', header):
        raise ValueError('Plugin version mismatch')
    if not 'Stable tag: ' + VERSION in (source / 'readme.txt').read_text():
        raise ValueError('Stable tag mismatch')
    for name in ('README.md', 'CHANGELOG.md', 'docs/github-release-notes.md'):
        if not VERSION in (source / name).read_text():
            raise ValueError(name + ' missing version')
    if not 'Requires Plugins: tickera-event-ticketing-system' in header:
        raise ValueError('Release validation failed')
    if not 'License: GPL-2.0-or-later' in header:
        raise ValueError('Release validation failed')

def verify(archive_path, source=None):
    with zipfile.ZipFile(archive_path) as archive:
        expected = [SLUG + '/' + name for name in sorted(FILES)]
        if not archive.namelist() == expected:
            raise ValueError('Archive allowlist/order mismatch')
        if not archive.testzip() is None:
            raise ValueError('CRC failure')
        for item in archive.infolist():
            if not item.external_attr >> 16 == 33188:
                raise ValueError('Unsafe permissions')
            if source:
                if not archive.read(item) == (source / item.filename[len(SLUG) + 1:]).read_bytes():
                    raise ValueError('Source mismatch')
    return True

def build(source, output, tag=None, require_clean=False):
    source = source.resolve()
    validate_versions(source)
    commit = None
    clean = False
    try:
        commit = git(source, 'rev-parse', 'HEAD')
        clean = not git(source, 'status', '--porcelain', '--untracked-files=all')
    except subprocess.CalledProcessError:
        pass
    if tag:
        if not tag == 'v' + VERSION:
            raise ValueError('Tag/version mismatch')
        if not git(source, 'rev-parse', tag + '^{commit}') == commit:
            raise ValueError('Tag is not checked out')
    if require_clean:
        if not (tag and commit and clean):
            raise ValueError('Release requires an existing checked-out tag and clean checkout')
    expected_php = {name for name in FILES if name.endswith('.php')}
    actual_php = {p.relative_to(source).as_posix() for p in (source / 'includes').rglob('*.php')} | {SLUG + '.php'}
    if not expected_php == actual_php:
        raise ValueError('Runtime PHP allowlist needs review')
    for name in FILES:
        path = source / name
        if not (path.is_file() and (not any((p.is_symlink() for p in [path, *path.parents] if p != source.parent)))):
            raise ValueError('Missing/symlinked runtime file: ' + name)
    output.mkdir(parents=True, exist_ok=True)
    path = output / (SLUG + '.zip')
    hashes = {}
    with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(FILES):
            data = (source / name).read_bytes()
            info = zipfile.ZipInfo(SLUG + '/' + name, (2026, 10, 15, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 33188 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, data, compresslevel=9)
            hashes[name] = digest(data)
    verify(path, source)
    checksum = digest(path.read_bytes())
    (output / (path.name + '.sha256')).write_text(checksum + '  ' + path.name + '\n')
    manifest = {'version': VERSION, 'tag': tag, 'source_commit': commit, 'clean_checkout': clean, 'archive': path.name, 'bytes': path.stat().st_size, 'sha256': checksum, 'files': hashes}
    (output / 'release-manifest.json').write_text(json.dumps(manifest, indent=2, sort_keys=True) + '\n')
    print(json.dumps({k: v for (k, v) in manifest.items() if k != 'files'}, indent=2))
    return manifest
if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--output', type=Path)
    parser.add_argument('--tag')
    parser.add_argument('--require-clean', action='store_true')
    args = parser.parse_args()
    build(args.source, args.output or args.source / 'dist', args.tag, args.require_clean)
