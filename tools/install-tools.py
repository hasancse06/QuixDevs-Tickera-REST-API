#!/usr/bin/env python3
"""Install checksum-pinned official lint/secret-scan binaries; stdlib only."""
import hashlib
import io
from pathlib import Path
import platform
import tarfile
import urllib.request

PINS = {
 ('actionlint', 'Linux', 'x86_64'): ('https://github.com/rhysd/actionlint/releases/download/v1.7.12/actionlint_1.7.12_linux_amd64.tar.gz', '8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8'),
 ('actionlint', 'Darwin', 'arm64'): ('https://github.com/rhysd/actionlint/releases/download/v1.7.12/actionlint_1.7.12_darwin_arm64.tar.gz', 'aba9ced2dee8d27fecca3dc7feb1a7f9a52caefa1eb46f3271ea66b6e0e6953f'),
 ('gitleaks', 'Linux', 'x86_64'): ('https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/gitleaks_8.30.1_linux_x64.tar.gz', '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'),
 ('gitleaks', 'Darwin', 'arm64'): ('https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/gitleaks_8.30.1_darwin_arm64.tar.gz', 'b40ab0ae55c505963e365f271a8d3846efbc170aa17f2607f13df610a9aeb6a5'),
}
if __name__ == '__main__':
 output = Path(__file__).resolve().parents[1] / '.work/tools'
 output.mkdir(parents=True, exist_ok=True)
 for name in ('actionlint', 'gitleaks'):
  url, checksum = PINS[(name, platform.system(), platform.machine())]
  data = urllib.request.urlopen(url, timeout=90).read()
  if hashlib.sha256(data).hexdigest() != checksum:
   raise RuntimeError('Checksum mismatch: ' + name)
  with tarfile.open(fileobj=io.BytesIO(data)) as archive:
   member = archive.getmember(name)
   if not member.isfile(): raise RuntimeError('Expected regular tool binary')
   (output / name).write_bytes(archive.extractfile(member).read())
  (output / name).chmod(0o700)
  print('Installed verified ' + name)
