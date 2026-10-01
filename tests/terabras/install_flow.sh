#!/usr/bin/env bash
#
# Terabras — end-to-end install & persistence test.
#
# Reproduces the scenario that must never regress:
#
#     docker compose down -v  ->  up  ->  run the wizard  ->  restart  ->  still installed
#
# and asserts the state every reported defect was about. It drives the real web
# wizard over HTTP (no fixtures), so it also covers the installer template overrides.
#
# Usage:
#     tests/terabras/install_flow.sh              # full run: wipes volumes, installs, restarts
#     KEEP_STACK=1 tests/terabras/install_flow.sh # leave the stack up afterwards
#
# Requires: docker compose, curl. Takes a few minutes (the DB init is the slow part).
set -uo pipefail

cd "$(dirname "$0")/../.."

COMPOSE=(docker compose -f docker-compose.terabras.yaml)
# Must match the default in docker-compose.terabras.yaml: provisioning renames the
# factory "glpi" account so the product never greets the user by another product's
# name. The rename only happens while the factory password is still in place.
ADMIN_LOGIN="${TERABRAS_ADMIN_USER:-admin}"
BASE_URL="${BASE_URL:-http://localhost:8081}"
WORK="$(mktemp -d)"
JAR="$WORK/cookies.txt"
trap 'rm -rf "$WORK"' EXIT

pass=0
fail=0

ok()   { printf '  \033[32mPASS\033[0m %s\n' "$1"; pass=$((pass + 1)); }
ko()   { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fail=$((fail + 1)); }
step() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }

# assert <description> <expected> <actual>
assert() {
  if [ "$2" = "$3" ]; then ok "$1"; else ko "$1 (expected '$2', got '$3')"; fi
}

# assert_contains <description> <needle> <file>
assert_contains() {
  if grep -qF -- "$2" "$3"; then ok "$1"; else ko "$1 (missing '$2')"; fi
}

# assert_not_contains <description> <needle> <file>
assert_not_contains() {
  if grep -qF -- "$2" "$3"; then ko "$1 (found '$2')"; else ok "$1"; fi
}

sql() { "${COMPOSE[@]}" exec -T db mariadb -uroot -pglpi glpi -N -B -e "$1" 2>/dev/null | tr -d '\r'; }

# ge <a> <b> -> "yes" when a >= b, "no" otherwise (and never a shell error on junk input)
ge() {
  case "$1$2" in (*[!0-9]*|'') echo no; return;; esac
  [ "$1" -ge "$2" ] && echo yes || echo no
}

# php_mb <ini-directive> -> its value in whole MB
php_mb() {
  "${COMPOSE[@]}" exec -T app php -r 'echo (int) rtrim(ini_get($argv[1]), "MmGg") * (stripos(ini_get($argv[1]), "G") !== false ? 1024 : 1);' -- "$1" 2>/dev/null | tr -d '\r'
}

# password_hash_of <login> -> the stored bcrypt hash
password_hash_of() { sql "SELECT password FROM glpi_users WHERE name='$1';"; }

# verifies <plaintext> <hash> -> "yes" when the password matches
verifies() {
  [ -n "$2" ] || { echo no; return; }
  GLPI_PW="$1" GLPI_HASH="$2" "${COMPOSE[@]}" exec -T -e GLPI_PW -e GLPI_HASH app \
    php -r 'echo password_verify(getenv("GLPI_PW"), getenv("GLPI_HASH")) ? "yes" : "no";' 2>/dev/null | tr -d '\r'
}

# can_log_in <login> <password> -> "yes" only when GLPI actually grants a session.
# This is the assertion that matters for item 11: a deactivated account keeps its old
# password hash, so only a real login attempt proves it cannot be used.
# GLPI answers a successful login with 302 -> /front/central.php (or the helpdesk
# equivalent) and a rejected one with 400.
can_log_in() {
  local jar="$WORK/login.jar"
  rm -f "$jar"
  curl -s -c "$jar" -b "$jar" "$BASE_URL/index.php" -o "$WORK/login-page.html"
  local token
  token="$(grep -o '_glpi_csrf_token" value="[^"]*"' "$WORK/login-page.html" | head -1 | sed 's/.*value="//;s/"//')"
  curl -s -c "$jar" -b "$jar" -X POST "$BASE_URL/front/login.php" \
    -d "login_name=$1" -d "login_password=$2" -d "noAUTO=1" \
    -d "_glpi_csrf_token=$token" -o /dev/null -D "$WORK/login-headers.txt"
  # GLPI answers a successful login with a redirect into /front/ — relative when
  # `url_base` matches the host, absolute when it does not.
  if grep -qiE "^location:[[:space:]]*(https?://[^/]+)?/front/" "$WORK/login-headers.txt"; then
    echo yes
  else
    echo no
  fi
}

form_csrf() {
  grep -o "_glpi_csrf_token[^>]*value=[\"'][^\"']*" "$1" | head -1 | sed "s/.*value=[\"']//"
}
meta_csrf() {
  grep -o 'glpi:csrf_token"[^>]*content="[^"]*"' "$1" | sed 's/.*content="//;s/"//'
}
wizard() { # wizard <output-file> <form-field>...
  local out="$1"; shift
  local args=()
  for kv in "$@"; do args+=(-d "$kv"); done
  # A browser sends a Referer, and GLPI's step 8 derives `url_base` from it. Without
  # one the instance records "http://localhost" and then emits absolute links to the
  # wrong host — so the test has to behave like a browser here.
  curl -s -c "$JAR" -b "$JAR" -e "$BASE_URL/install/install.php" \
    -X POST "$BASE_URL/install/install.php" "${args[@]}" -o "$out"
}

# ---------------------------------------------------------------------------
step "1. Clean slate (docker compose down -v)"
# ---------------------------------------------------------------------------
"${COMPOSE[@]}" down -v >/dev/null 2>&1
"${COMPOSE[@]}" up -d --build >/dev/null 2>&1 || { echo "stack failed to start"; exit 1; }

for _ in $(seq 1 90); do
  [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/install/install.php")" = "200" ] && break
  sleep 2
done

# The wizard connects to MariaDB directly; starting before the server finished its
# first-run initialisation makes step 3 fail with a connection error.
for _ in $(seq 1 90); do
  "${COMPOSE[@]}" exec -T db mariadb -uglpi -pglpi -e "SELECT 1" >/dev/null 2>&1 && break
  sleep 2
done

assert "config volume starts empty (no config_db.php)" \
  "absent" \
  "$("${COMPOSE[@]}" exec -T app sh -c 'test -f /var/glpi/config/config_db.php && echo present || echo absent' | tr -d '\r')"

# ---------------------------------------------------------------------------
step "2. Run the Terabras wizard over HTTP"
# ---------------------------------------------------------------------------
curl -s -c "$JAR" -b "$JAR" "$BASE_URL/install/install.php" -o "$WORK/s0.html"
wizard "$WORK/s1.html" "install=lang_select" "language=pt_BR"       "_glpi_csrf_token=$(form_csrf "$WORK/s0.html")"
wizard "$WORK/s2.html" "install=License"                            "_glpi_csrf_token=$(form_csrf "$WORK/s1.html")"
wizard "$WORK/s3.html" "install=Etape_0" "update=no"                "_glpi_csrf_token=$(form_csrf "$WORK/s2.html")"
wizard "$WORK/s4.html" "install=Etape_1" "update=no"                "_glpi_csrf_token=$(form_csrf "$WORK/s3.html")"
wizard "$WORK/s5.html" "install=Etape_2" "update=no" "db_host=db" "db_user=glpi" "db_pass=glpi" \
                                                                    "_glpi_csrf_token=$(form_csrf "$WORK/s4.html")"
wizard "$WORK/s6.html" "install=Etape_3" "databasename=glpi"        "_glpi_csrf_token=$(form_csrf "$WORK/s5.html")"

# Step 4 hands the actual schema creation to an ASYNCHRONOUS job: the endpoint
# answers immediately with a progress key and the work continues in the background,
# so the wizard cannot be advanced until that job reports `ended_at`.
curl -s -c "$JAR" -b "$JAR" -X POST "$BASE_URL/Install/InitDatabase" \
  -H "Accept: application/json" -H "X-Requested-With: XMLHttpRequest" \
  -H "X-Glpi-Csrf-Token: $(meta_csrf "$WORK/s6.html")" \
  -H "Content-Type: application/x-www-form-urlencoded;" \
  --max-time 60 -o "$WORK/initdb.txt"

# The key is derived from the session id, whose alphabet depends on
# session.sid_bits_per_character — it is NOT necessarily hexadecimal. Strip only
# whitespace, or the key gets mangled and every poll 404s (which silently looks
# like "finished" and races the wizard into a half-built schema).
PROGRESS_KEY="$(tr -d '[:space:]' < "$WORK/initdb.txt")"
printf '  waiting for the database initialisation job (%s)…' "${PROGRESS_KEY:0:8}"
for _ in $(seq 1 450); do
  curl -s -c "$JAR" -b "$JAR" "$BASE_URL/progress/check/$PROGRESS_KEY" -o "$WORK/progress.json"
  grep -q '"ended_at":null' "$WORK/progress.json" || break
  printf '.'
  sleep 2
done
printf '\n'
assert_not_contains "database initialisation job did not fail" '"failed":true' "$WORK/progress.json"

TABLES="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='glpi';")"
assert "database schema created" "yes" "$(ge "${TABLES:-0}" 100)"

wizard "$WORK/step6.html" "install=Etape_4" "_glpi_csrf_token=$(form_csrf "$WORK/s6.html")"

step "   Installer step 4 (telemetry / promotional content)"
assert_not_contains "telemetry checkbox is NOT pre-checked"        "name='send_stats' checked" "$WORK/step6.html"
assert_not_contains "telemetry checkbox is NOT pre-checked (alt)"  'name="send_stats" checked' "$WORK/step6.html"
assert_not_contains "no GLPI registration-form promo"              "telemetry/reference"        "$WORK/step6.html"
assert_contains     "step 4 skips the GLPI Network step"           'name="install" value="Etape_6"' "$WORK/step6.html"

# Continue WITHOUT ticking send_stats, exactly as a click-through would.
wizard "$WORK/step8.html" "install=Etape_6" "_glpi_csrf_token=$(form_csrf "$WORK/step6.html")"

step "   Installer final step"
assert_not_contains "no glpi/glpi default credentials shown"     "glpi/glpi"     "$WORK/step8.html"
assert_not_contains "no tech/tech default credentials shown"     "tech/tech"     "$WORK/step8.html"
assert_not_contains "no post-only/postonly credentials shown"    "postonly"      "$WORK/step8.html"
assert_contains     "generated administrator password is shown"  "Conta de administrador" "$WORK/step8.html"

# The password is HTML-escaped in the page (it may contain & < > " '), so read the
# <code> elements through an unescaping pass rather than comparing raw markup.
code_element() { # code_element <nth>
  python3 - "$WORK/step8.html" "$1" <<'PYEOF'
import html, re, sys
page = open(sys.argv[1], encoding='utf-8', errors='replace').read()
items = re.findall(r'<code>(.*?)</code>', page, re.S)
idx = int(sys.argv[2]) - 1
print(html.unescape(items[idx]).strip() if len(items) > idx else '')
PYEOF
}
ADMIN_LOGIN_SHOWN="$(code_element 1)"
ADMIN_PASS="$(code_element 2)"
assert "final screen shows the provisioned administrator login" "$ADMIN_LOGIN" "$ADMIN_LOGIN_SHOWN"
assert "administrator password captured from final screen" "20" "${#ADMIN_PASS}"

# ---------------------------------------------------------------------------
step "3. Post-install state"
# ---------------------------------------------------------------------------
assert "config_db.php lives on the config VOLUME, not in the source tree" \
  "present" \
  "$("${COMPOSE[@]}" exec -T app sh -c 'test -f /var/glpi/config/config_db.php && echo present || echo absent' | tr -d '\r')"

assert "timezone support enabled in config_db.php (item 1)" \
  "yes" \
  "$("${COMPOSE[@]}" exec -T app sh -c "grep -q 'use_timezones = true' /var/glpi/config/config_db.php && echo yes || echo no" | tr -d '\r')"

assert "timezone tables readable by the application DB user (item 1)" \
  "yes" \
  "$([ "$(sql 'SELECT COUNT(*) FROM mysql.time_zone_name;' 2>/dev/null || echo 0)" -gt 0 ] && echo yes || echo no)"

UPLOAD_MB="$(php_mb upload_max_filesize)"
POST_MB="$(php_mb post_max_size)"
DOC_MAX="$(sql "SELECT value FROM glpi_configs WHERE context='core' AND name='document_max_size';")"

assert "PHP upload_max_filesize >= 100 MB (item 6)"  "yes" "$(ge "${UPLOAD_MB:-0}" 100)"
assert "PHP post_max_size >= upload_max_filesize (item 6)" "yes" "$(ge "${POST_MB:-0}" "${UPLOAD_MB:-0}")"
assert "GLPI document_max_size >= 100 MB (item 6)"   "yes" "$(ge "${DOC_MAX:-0}" 100)"
assert "GLPI never advertises more than PHP accepts (item 6)" "yes" "$(ge "${UPLOAD_MB:-0}" "${DOC_MAX:-0}")"

assert "recorded public URL matches where the instance was installed" \
  "$BASE_URL" "$(sql "SELECT value FROM glpi_configs WHERE context='core' AND name='url_base';")"

assert "marketplace prompt answered, so Setup > Plugins does not ask (item 5)" \
  "3" "$(sql "SELECT value FROM glpi_configs WHERE context='core' AND name='marketplace_replace_plugins';")"

assert "telemetry cron task stays disabled (item 8)" \
  "0" "$(sql "SELECT state FROM glpi_crontasks WHERE name='telemetry';")"

assert "single 'initialized_rules_collections' history entry (item 3)" \
  "1" "$(sql "SELECT COUNT(*) FROM glpi_logs WHERE itemtype='Config' AND old_value LIKE 'initialized_rules_collections%';")"

assert "default service accounts deactivated (item 11)" \
  "0" "$(sql "SELECT COUNT(*) FROM glpi_users WHERE name IN ('tech','normal','post-only') AND is_active=1;")"

step "   Default passwords must no longer authenticate (item 11)"
for creds in "glpi:glpi" "tech:tech" "normal:normal" "post-only:postonly"; do
  u="${creds%%:*}"; p="${creds##*:}"
  assert "'$u/$p' cannot log in" "no" "$(can_log_in "$u" "$p")"
done

# And the administrator's factory password specifically must be gone, not merely
# unusable: it is the one account that stays active.
assert "the administrator account was renamed away from 'glpi' (item 11)" \
  "1" "$(sql "SELECT COUNT(*) FROM glpi_users WHERE name='$ADMIN_LOGIN';")"
assert "no account still carries the factory 'glpi' password (item 11)" \
  "no" "$(verifies glpi "$(password_hash_of "$ADMIN_LOGIN")")"

# Positive control: without this, every "cannot log in" above could be passing for
# the wrong reason (a broken probe rather than a rejected credential).
assert "the credentials the wizard issued DO log in" \
  "yes" "$(can_log_in "$ADMIN_LOGIN" "$ADMIN_PASS")"

assert "branding plugin active out of the box" \
  "1" "$(sql "SELECT state FROM glpi_plugins WHERE directory='terabras';")"

# ---------------------------------------------------------------------------
step "4. Restart the stack — the instance must stay installed (item 7)"
# ---------------------------------------------------------------------------
"${COMPOSE[@]}" down >/dev/null 2>&1      # containers removed, volumes kept
"${COMPOSE[@]}" up -d >/dev/null 2>&1
for _ in $(seq 1 60); do
  [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/index.php")" = "200" ] && break
  sleep 2
done

curl -s -L "$BASE_URL/index.php" -o "$WORK/after_restart.html"
assert_not_contains "no 'GLPI must be configured' screen after restart"  "install.php" "$WORK/after_restart.html"
assert_contains     "login page is served after restart (item 12)"       "login_name"  "$WORK/after_restart.html"

assert "config_db.php survived the container replacement" \
  "present" \
  "$("${COMPOSE[@]}" exec -T app sh -c 'test -f /var/glpi/config/config_db.php && echo present || echo absent' | tr -d '\r')"

assert "restart did not re-run rule initialisation (item 3)" \
  "1" "$(sql "SELECT COUNT(*) FROM glpi_logs WHERE itemtype='Config' AND old_value LIKE 'initialized_rules_collections%';")"

ADMIN_HASH_AFTER="$(password_hash_of "$ADMIN_LOGIN")"
assert "restart did not reset the administrator password (item 11)" \
  "no" "$(verifies glpi "$ADMIN_HASH_AFTER")"

if [ -n "${ADMIN_PASS:-}" ]; then
  assert "the password shown by the wizard still authenticates" \
    "yes" "$(verifies "$ADMIN_PASS" "$ADMIN_HASH_AFTER")"
fi

# ---------------------------------------------------------------------------
step "Summary"
# ---------------------------------------------------------------------------
printf '  %d passed, %d failed\n\n' "$pass" "$fail"

[ "${KEEP_STACK:-0}" = "1" ] || "${COMPOSE[@]}" down >/dev/null 2>&1

[ "$fail" -eq 0 ]
