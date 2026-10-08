#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p .runtime
python3 - <<'PY'
import re
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root=Path('plugin')
version=re.search(r'\* Version: ([\w.-]+)',(root/'objectif-infirmiere/objectif-infirmiere.php').read_text()).group(1)
output=Path('.runtime')/f'objectif-infirmiere-{version}.zip'
with ZipFile(output,'w',ZIP_DEFLATED) as archive:
    for p in sorted((root/'objectif-infirmiere').rglob('*')):
        if p.is_file(): archive.write(p,p.relative_to(root))
print(f'Archive créée : {output}')
PY
