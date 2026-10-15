#!/usr/bin/env python3
"""Fail on every official Plugin Check finding; no warning allowlist."""
import json
from pathlib import Path
import sys
raw = Path(sys.argv[1]).read_text().strip()
if not raw:
 raise SystemExit('Plugin Check returned no JSON evidence')
if raw == 'Success: Checks complete. No errors found.':
 print('Plugin Check: zero findings')
 raise SystemExit(0)
findings = json.loads(raw)
if findings:
 raise SystemExit('Plugin Check reported findings; inspect the JSON report')
print('Plugin Check: zero findings')
