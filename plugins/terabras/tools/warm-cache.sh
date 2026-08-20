#!/usr/bin/env sh
# Terabras — warm GLPI's compiled-SCSS cache after a deploy or cache wipe.
#
# Core css/glpi.scss compiles in ~29s cold, right on the 30s max_execution_time,
# so the FIRST request after a cache wipe can 500. Run this once post-deploy so no
# real user ever hits a cold compile.
#
# Usage: BASE_URL=https://support.terabras.com sh warm-cache.sh
set -eu
BASE_URL="${BASE_URL:-http://localhost:8080}"

# Discover the current asset version hash from the login page.
V="$(curl -fsS "$BASE_URL/index.php" | grep -oE 'v=[a-f0-9]{40}' | head -1 | cut -d= -f2 || true)"
if [ -z "${V:-}" ]; then
  echo "warm-cache: could not read asset version from $BASE_URL/index.php" >&2
  exit 1
fi

for f in css/glpi.scss css/core_palettes.scss; do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 180 "$BASE_URL/front/css.php?file=${f}&v=${V}")
  echo "warm-cache: $f -> HTTP $code"
done
echo "warm-cache: done (v=$V)"
