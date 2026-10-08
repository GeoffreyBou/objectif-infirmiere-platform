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
# Reuse the platform HTTPS proxy and system trust, without printing credentials.
python3 - <<'PY_PROXY'
import os,socket
from pathlib import Path
from urllib.parse import urlparse
p=urlparse(os.environ.get('HTTPS_PROXY') or os.environ.get('HTTP_PROXY') or '')
lines=[]
if p.hostname:
    lines=[f'OI_PROXY_HOST={p.hostname}',f'OI_PROXY_IP={socket.gethostbyname(p.hostname)}']
Path('.runtime/proxy.env').write_text('\n'.join(lines)+'\n')
Path('.runtime/cloud-trust.php').write_text("<?php\ndefined('ABSPATH') || exit;\nadd_filter('http_request_args', function ($args) { $args['sslcertificates']='/etc/ssl/certs/ca-certificates.crt'; return $args; });\n")
Path('.runtime/cloud-trust.php').chmod(0o644)
PY_PROXY
mkdir -p .runtime/mail
scripts/dc.sh up -d --wait db wordpress
# Private shared group: WordPress writes; local browser tests can inspect their own mail.
scripts/dc.sh exec -T -u 0 wordpress sh -c 'chown 33:"$1" /var/oi-mail && chmod 2770 /var/oi-mail' sh "$(id -g)"
if ! scripts/dc.sh run --rm cli wp core is-installed; then
  # Password remains in a local private file, never in tracked files or logs.
  scripts/dc.sh run --rm cli wp core install --url=http://127.0.0.1:8080 --title='Objectif Infirmière — Développement' --admin_user=oi_local_admin --admin_email=admin@example.invalid --admin_password="$(sed -n 's/^OI_LOCAL_ADMIN_PASSWORD=//p' .runtime/local.env)" --skip-email > .runtime/install.log
  scripts/dc.sh run --rm cli wp option update home http://127.0.0.1:8080
  scripts/dc.sh run --rm cli wp option update siteurl http://127.0.0.1:8080
fi
# Named volumes can retain an older core when the pinned image changes.
if [[ "$(scripts/dc.sh wp core version)" != "7.1.2" ]]; then
  umask 077
  scripts/dc.sh wp db export - > .runtime/pre-core-upgrade.sql
  scripts/dc.sh wp core update --version=7.1.2
  scripts/dc.sh wp core update-db
  scripts/dc.sh wp core verify-checksums
fi
scripts/dc.sh run --rm cli wp plugin activate objectif-infirmiere

scripts/dc.sh wp eval 'OI_App::install_pages();'
