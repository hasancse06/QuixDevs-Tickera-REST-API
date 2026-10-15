import importlib.util
from pathlib import Path
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[2]
(ROOT / '.work').mkdir(exist_ok=True)
spec = importlib.util.spec_from_file_location('packager', ROOT / 'tools/build-release.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class PackageTests(unittest.TestCase):
 def test_deterministic_and_exact_runtime_allowlist(self):
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   target = Path(temporary)
   first = module.build(ROOT, target)
   raw = (target / (module.SLUG + '.zip')).read_bytes()
   second = module.build(ROOT, target)
   self.assertEqual(raw, (target / (module.SLUG + '.zip')).read_bytes())
   self.assertEqual(first, second)
   self.assertTrue(module.verify(target / first['archive'], ROOT))
 def test_rejects_extra_and_traversal_entries(self):
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   path = Path(temporary) / 'bad.zip'
   for name in ('../secret', module.SLUG + '/vendor/autoload.php'):
    with zipfile.ZipFile(path, 'w') as archive: archive.writestr(name, 'x')
    with self.assertRaises(ValueError): module.verify(path)
 def test_release_requires_existing_tag_clean_git(self):
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   with self.assertRaises((ValueError, module.subprocess.CalledProcessError)):
    module.build(ROOT, Path(temporary), 'v9.9.9', True)

 def test_clean_release_mode_refuses_missing_git_provenance(self):
  with tempfile.TemporaryDirectory(dir=ROOT / '.work') as temporary:
   with self.assertRaises(ValueError):
    module.build(ROOT,Path(temporary),require_clean=True)
