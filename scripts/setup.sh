#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p .runtime
if [[ ! -f .runtime/local.env ]]; then
  umask 077
  python3 - <<'PY'
import secrets
from pathlib import Path
Path('.runtime/local.env').write_text('\n'.join(f'{k}={secrets.token_hex(24)}' for k in ['OI_LOCAL_DB_PASSWORD','OI_LOCAL_ROOT_PASSWORD','OI_LOCAL_ADMIN_PASSWORD'])+'\n')
PY
fi
scripts/dc.sh up -d --wait db wordpress
if ! scripts/dc.sh run --rm cli wp core is-installed; then
  # Password remains in a local private file, never in tracked files or logs.
  scripts/dc.sh run --rm cli wp core install --url=http://127.0.0.1:8080 --title='Objectif Infirmière — Développement' --admin_user=oi_local_admin --admin_email=admin@example.invalid --admin_password="$(sed -n 's/^OI_LOCAL_ADMIN_PASSWORD=//p' .runtime/local.env)" --skip-email > .runtime/install.log
  scripts/dc.sh run --rm cli wp option update home http://127.0.0.1:8080
  scripts/dc.sh run --rm cli wp option update siteurl http://127.0.0.1:8080
fi
scripts/dc.sh run --rm cli wp plugin activate objectif-infirmiere
