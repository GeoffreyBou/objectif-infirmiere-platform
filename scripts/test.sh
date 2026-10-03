#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Local development only. DB tests create/delete their own fixtures.
scripts/dc.sh exec -T wordpress bash -o pipefail -c 'find wp-content/plugins/objectif-infirmiere -name "*.php" -print0 | while IFS= read -r -d "" file; do php -l "$file" || exit $?; done'
node --check plugin/objectif-infirmiere/assets/app.js
for suite in socle experience stripe ai revision; do
  scripts/dc.sh wp eval-file "/tests/$suite.php"
done
# Always make a fresh browser user so state cannot leak between runs.
if [[ -f .runtime/browser-user.json ]]; then
  previous_id=$(python3 -c 'import json; print(int(json.load(open(".runtime/browser-user.json"))["id"]))')
  scripts/dc.sh wp user delete "$previous_id" --yes >/dev/null
fi
scripts/dc.sh wp eval-file /oi-scripts/browser-user.php > .runtime/browser-user.json
npm --cache /workspace/.npm-cache run test:browser
