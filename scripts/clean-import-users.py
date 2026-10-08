#!/usr/bin/env python3
"""Clean only the isolated local import administrator and its own fixture posts."""
import json, subprocess
from pathlib import Path
p=Path('.runtime/browser-import-user.json')
if p.exists():
    account=json.loads(p.read_text())
    subprocess.run(['scripts/dc.sh','wp','eval-file','/tests/browser-imports.php','delete',str(int(account['id']))],check=True)
    p.unlink()
