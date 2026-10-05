#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p .runtime
python3 - <<'PY'
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root=Path('plugin')
with ZipFile('.runtime/objectif-infirmiere-0.2.1.zip','w',ZIP_DEFLATED) as archive:
    for p in sorted((root/'objectif-infirmiere').rglob('*')):
        if p.is_file(): archive.write(p,p.relative_to(root))
print('Archive créée : .runtime/objectif-infirmiere-0.2.1.zip')
PY
