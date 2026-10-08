#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Local development only. DB tests create/delete their own fixtures.
scripts/dc.sh exec -T wordpress bash -o pipefail -c 'find wp-content/plugins/objectif-infirmiere -name "*.php" -print0 | while IFS= read -r -d "" file; do php -l "$file" || exit $?; done'
for js in plugin/objectif-infirmiere/assets/*.js; do node --check "$js"; done
for suite in socle experience stripe ai revision library imports qcm goals auth credits premium-refunds freemium-admin; do
  scripts/dc.sh wp eval-file "/tests/$suite.php"
done
# Preserve unrelated users even if a restored DB makes ignored fixture IDs stale.
python3 scripts/clean-test-users.py browser
python3 scripts/clean-test-users.py signup
python3 scripts/clean-test-users.py freemium
python3 scripts/clean-import-users.py
trap 'python3 scripts/clean-test-users.py signup; python3 scripts/clean-test-users.py freemium; python3 scripts/clean-import-users.py' EXIT
scripts/dc.sh wp eval-file /oi-scripts/browser-user.php > .runtime/browser-user.json
chmod 600 .runtime/browser-user.json
scripts/dc.sh wp eval-file /tests/browser-imports.php setup > .runtime/browser-import-user.json
chmod 600 .runtime/browser-import-user.json
npm --cache /workspace/.npm-cache run test:browser
