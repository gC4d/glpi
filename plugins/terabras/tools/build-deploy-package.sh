#!/usr/bin/env bash
#
# Terabras — build an upload-ready release archive.
#
# For deployments WITHOUT shell access (shared hosting: FTP / cPanel File
# Manager only). Everything that normally needs a terminal on the server is done
# here instead, and the result is a tree that only has to be uploaded.
#
# What this replaces, and with what:
#
#   composer install            -> baked into the archive
#   npm run build               -> baked into the archive
#   tools:locales:compile       -> baked into the archive (.mo files)
#   build:compile_scss          -> baked into the archive (public/css_compiled/)
#   database:install            -> the WEB wizard, /install/install.php
#   plugins:terabras:postinstall-> runs inside the wizard's final step already
#   php.ini limits              -> .user.ini (+ .htaccess fallback), shipped here
#
#   cron                        -> still needs the host's scheduler UI, or GLPI's
#                                  internal mode. Nothing can bake that in.
#
# Usage:
#     plugins/terabras/tools/build-deploy-package.sh [--out DIR] [--skip-build]
#
# Options:
#     --out DIR       where to write the archive (default: ./dist)
#     --skip-build    trust the artefacts already in the working tree
#     --format F      tar.gz | zip | both   (default: both)
#
# Environment (all optional, written into the shipped .user.ini):
#     PHP_UPLOAD_MAX_FILESIZE   default 128M
#     PHP_POST_MAX_SIZE         default 144M
#     PHP_MEMORY_LIMIT          default 512M
#     PHP_MAX_EXECUTION_TIME    default 300
set -euo pipefail

cd "$(dirname "$0")/../../.."
ROOT="$PWD"
OUT="$ROOT/dist"
SKIP_BUILD=0
# cPanel's File Manager extracts .zip most reliably; .tar.gz keeps the archive
# roughly half the size. Default to both so whoever uploads can pick.
FORMAT="both"

while [ $# -gt 0 ]; do
  case "$1" in
    --out) OUT="$2"; shift 2 ;;
    --skip-build) SKIP_BUILD=1; shift ;;
    --format) FORMAT="$2"; shift 2 ;;
    -h|--help) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
done

log() { printf '[build-deploy] %s\n' "$*"; }
die() { printf '[build-deploy] ERROR: %s\n' "$*" >&2; exit 1; }

# The version is the NAME of the (empty) marker file under version/.
VERSION="$(basename "$(ls "$ROOT"/version/* 2>/dev/null | head -1)" 2>/dev/null || echo unknown)"
[ -n "$VERSION" ] || VERSION=unknown
STAMP="$(date +%Y%m%d-%H%M)"
NAME="terabras-${VERSION}-${STAMP}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# ---------------------------------------------------------------------------
# 1. Build the artefacts the repository deliberately does not version.
# ---------------------------------------------------------------------------
if [ "$SKIP_BUILD" -eq 0 ]; then
  command -v composer >/dev/null || die "composer not found (use --skip-build to package the tree as-is)"
  command -v npm >/dev/null || die "npm not found (use --skip-build to package the tree as-is)"

  log "composer install (no-dev)…"
  composer install --no-dev --optimize-autoloader --no-interaction

  log "npm ci + build…"
  npm ci
  npm run build

  log "compiling translation catalogues…"
  php bin/console --no-interaction tools:locales:compile

  log "precompiling stylesheets…"
  php bin/console --no-interaction build:compile_scss
fi

# ---------------------------------------------------------------------------
# 2. Refuse to ship a tree that would fail on the server the way a plain
#    `git clone` does. These are exactly the paths .gitignore excludes.
# ---------------------------------------------------------------------------
required=(
  "vendor/autoload.php"
  "public/lib"
  "public/build/vue"
  "public/css_compiled"
  "locales/pt_BR.mo"
)
for path in "${required[@]}"; do
  [ -e "$ROOT/$path" ] || die "missing build artefact: $path — run without --skip-build"
done
log "build artefacts present."

# ---------------------------------------------------------------------------
# 3. Stage the tree.
#
# Excludes development-only material: the container stack, the test suites, the
# brand kit, the node toolchain and the repository metadata. `.git` matters
# beyond size — GLPI derives GLPI_INSTALL_MODE from `is_dir(.git)`, so shipping
# it makes Setup > General report the product as a GIT checkout.
# ---------------------------------------------------------------------------
log "staging…"
# tar rather than rsync: it is present everywhere (minimal CI images and the
# project's own PHP container do not ship rsync).
mkdir -p "$STAGE/$NAME"
tar -cf - -C "$ROOT" \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./node_modules' \
  --exclude='./dist' \
  --exclude='./tests' \
  --exclude='./.docker' \
  --exclude='./docker-compose*.yaml' \
  --exclude='./.dockerignore' \
  --exclude='./.devcontainer' \
  --exclude='./TERABRAS_IdentidadeVisual*' \
  --exclude='./.phpstan-baseline*' \
  --exclude='./phpstan.neon*' \
  --exclude='./psalm.xml' \
  --exclude='./phpunit.xml.dist' \
  --exclude='./playwright.config.ts' \
  --exclude='./.php-cs-fixer*' \
  --exclude='./rector.php' \
  --exclude='./Makefile' \
  --exclude='./config/config_db.php' \
  --exclude='./config/config_db.php.bak' \
  --exclude='./config/glpicrypt.key' \
  --exclude='./config/oauth.*' \
  --exclude='./files/_*' \
  . | tar -xf - -C "$STAGE/$NAME"

# The installer must find config/ and files/ present and writable.
mkdir -p "$STAGE/$NAME/config" "$STAGE/$NAME/files"

# Pre-deploy the translation overrides.
#
# Provisioning copies these into files/_locales/ too, but on this deployment it
# only runs inside the wizard's FINAL step — the same request that already loaded
# the translator, so that last screen would render in English. Shipping them
# in place means the catalogue is complete before the very first request.
mkdir -p "$STAGE/$NAME/files/_locales/core"
cp "$ROOT/plugins/terabras/locales/core/"*.php "$STAGE/$NAME/files/_locales/core/"

# ---------------------------------------------------------------------------
# 3b. Rebuild the autoloader without dev dependencies.
#
# A working tree is normally installed WITH dev dependencies, and that
# autoloader eagerly requires tests/src/autoload/functions.php — a path this
# package deliberately does not ship. Uploading such a tree produces a fatal on
# the first request, which is exactly the class of failure this script exists to
# prevent. Re-dumping against the staged copy leaves the developer's own vendor/
# untouched.
# ---------------------------------------------------------------------------
if command -v composer >/dev/null && command -v php >/dev/null; then
  log "re-dumping the autoloader without dev dependencies…"
  ( cd "$STAGE/$NAME" && composer dump-autoload --no-dev --optimize --no-interaction --quiet )
fi

# Verify, with nothing but the shell, that every project-relative file the
# autoloader eagerly requires actually ships. `$vendorDir` entries are safe by
# construction (vendor/ is shipped whole); only `$baseDir` ones can escape.
autoload_files="$STAGE/$NAME/vendor/composer/autoload_files.php"
missing_autoload=""
if [ -f "$autoload_files" ]; then
  while IFS= read -r rel; do
    [ -n "$rel" ] || continue
    [ -e "$STAGE/$NAME/$rel" ] || missing_autoload="${missing_autoload}    ${rel}"$'\n'
  done <<EOF
$(grep -oE "\\\$baseDir \. '/[^']+'" "$autoload_files" 2>/dev/null | sed "s|.*'/\(.*\)'|\1|")
EOF
fi

if [ -n "$missing_autoload" ]; then
  printf '%s\n' "[build-deploy] ERROR: the autoloader requires files this package does not ship:" >&2
  printf '%s' "$missing_autoload" >&2
  die "vendor/ was installed WITH dev dependencies. Re-run with composer and php on PATH (the script then re-dumps it), or run 'composer install --no-dev' first."
fi
log "autoloader resolves inside the package."

# ---------------------------------------------------------------------------
# 4. PHP limits, as files rather than as a terminal.
#
# `.user.ini` is read by PHP-FPM/CGI (the usual shared-hosting setup, and what
# the Remi packages run); `.htaccess` covers the mod_php case. Both are shipped
# because we cannot know which one the host uses, and the irrelevant one is
# ignored rather than fatal.
#
# These values are what GLPI's `document_max_size` is aligned to by provisioning,
# so the advertised upload limit cannot exceed what the stack accepts.
# ---------------------------------------------------------------------------
upload="${PHP_UPLOAD_MAX_FILESIZE:-128M}"
post="${PHP_POST_MAX_SIZE:-144M}"
memory="${PHP_MEMORY_LIMIT:-512M}"
exec_time="${PHP_MAX_EXECUTION_TIME:-300}"

cat > "$STAGE/$NAME/public/.user.ini" <<INI
; Terabras — PHP limits for hosts without shell access.
; Read by PHP-FPM / PHP-CGI. Changes can take up to 5 minutes to apply
; (user_ini.cache_ttl). If the host runs mod_php instead, .htaccess below applies.
upload_max_filesize = ${upload}
post_max_size = ${post}
memory_limit = ${memory}
max_execution_time = ${exec_time}
max_input_time = ${exec_time}
max_file_uploads = 50

; Session cookie hardening. Set session.cookie_secure = 1 once the site is
; served over HTTPS — leaving it on while serving plain HTTP breaks every session.
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.cookie_secure = 0
INI
cp "$STAGE/$NAME/public/.user.ini" "$STAGE/$NAME/.user.ini"

cat > "$STAGE/$NAME/public/.htaccess.terabras-php" <<HT
# Terabras — PHP limits for hosts running mod_php.
# Rename to .htaccess (or append to the existing one) ONLY if .user.ini had no
# effect. On PHP-FPM/CGI these directives cause a 500, which is why they are not
# enabled by default.
<IfModule mod_php.c>
    php_value upload_max_filesize ${upload}
    php_value post_max_size ${post}
    php_value memory_limit ${memory}
    php_value max_execution_time ${exec_time}
    php_value max_input_time ${exec_time}
    php_value max_file_uploads 50

    # Same session hardening as .user.ini. Set session.cookie_secure to 1 once
    # the site is served over HTTPS; leaving it on over plain HTTP breaks every
    # session.
    php_flag session.cookie_httponly 1
    php_value session.cookie_samesite Lax
    php_flag session.cookie_secure 0
</IfModule>
HT

# ---------------------------------------------------------------------------
# 5. A template for moving the writable directories out of the document root.
#    Shipped disabled: the paths are deployment-specific.
# ---------------------------------------------------------------------------
cat > "$STAGE/$NAME/config/local_define.php.example" <<'DEF'
<?php

/**
 * Terabras — host-specific paths. Rename to `local_define.php` to activate.
 *
 * GLPI reads this before anything else (\Glpi\Application\SystemConfigurator), so
 * it is how a deployment without environment variables relocates its writable
 * directories. Putting them OUTSIDE the document root means the configuration,
 * the encryption key and every uploaded document stop being reachable over HTTP.
 *
 * Adjust the paths to the account, create the directories, and make them
 * writable by the web server user.
 */

define('GLPI_CONFIG_DIR', '/home/CHANGEME/glpi-data/config');
define('GLPI_VAR_DIR', '/home/CHANGEME/glpi-data/files');
DEF

# ---------------------------------------------------------------------------
# 6. Archive.
# ---------------------------------------------------------------------------
mkdir -p "$OUT"
files="$(find "$STAGE/$NAME" -type f | wc -l)"
produced=""

if [ "$FORMAT" = "tar.gz" ] || [ "$FORMAT" = "both" ]; then
  log "packing tar.gz…"
  tar -czf "$OUT/$NAME.tar.gz" -C "$STAGE" "$NAME"
  produced="${produced}  $OUT/$NAME.tar.gz ($(du -h "$OUT/$NAME.tar.gz" | cut -f1))"$'\n'
fi

if [ "$FORMAT" = "zip" ] || [ "$FORMAT" = "both" ]; then
  if command -v zip >/dev/null; then
    log "packing zip…"
    ( cd "$STAGE" && zip -qr "$OUT/$NAME.zip" "$NAME" )
    produced="${produced}  $OUT/$NAME.zip ($(du -h "$OUT/$NAME.zip" | cut -f1))"$'\n'
  else
    log "WARNING: zip not installed; only the tar.gz was produced."
  fi
fi

[ -n "$produced" ] || die "no archive was produced (check --format)"
log "done ($files files):"
printf '%s' "$produced"
echo
echo "Next, on the host (no shell required):"
echo
echo "  1. Upload and extract so that the document root IS the package's public/ directory."
echo "  2. Make config/ and files/ writable by the user PHP runs as (on cPanel that is"
echo "     the account user, so the uploaded ownership is already correct)."
echo "  3. APPLY THE PHP LIMITS BEFORE RUNNING THE WIZARD. The installer records the"
echo "     document size limit from whatever PHP reports at that moment, and on this"
echo "     deployment provisioning does not run again to correct it later."
echo "       - PHP-FPM / CGI (most shared hosting): public/.user.ini is already in"
echo "         place. It can take up to 5 minutes to take effect."
echo "       - mod_php: .user.ini is IGNORED. Rename"
echo "         public/.htaccess.terabras-php to .htaccess (or append its contents to"
echo "         the existing one)."
echo "       - cPanel also exposes these under MultiPHP INI Editor."
echo "     Confirm on the wizard's requirements step that the memory limit is honoured."
echo "  4. Open https://<host>/install/install.php and run the wizard. It installs the"
echo "     database AND runs the Terabras provisioning: timezones, product"
echo "     configuration, branding plugin, and removal of the default credentials."
echo "  5. Save the administrator password from the final screen - it is shown once."
echo "  6. Schedule cron through the host panel, every 5 minutes:"
echo "       php <path>/front/cron.php"
echo "     With no scheduler, leave GLPI internal mode on (Setup > General >"
echo "     Automatic actions); tasks then run on page loads."
