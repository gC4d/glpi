# Terabras — divergence ledger

Terabras is a **product built on GLPI** (soft fork), delivered as SaaS. This file
is the single source of truth for **every place we diverge from upstream GLPI**, so
that cherry-picking upstream security fixes stays predictable.

**Rule:** if you touch anything outside `plugins/terabras/` or `templates.terabras/`,
it MUST be listed here.

Upstream: `https://github.com/glpi-project/glpi.git` (base: GLPI 11.0.x).
Delivery: SaaS (GPLv3 imposes no source-disclosure for hosting). Update policy:
cherry-pick critical/security fixes only.

---

## 1. Core file edits (keep to the absolute minimum)

| File | Change | Why it can't be a plugin | Risk on upstream merge |
|------|--------|--------------------------|------------------------|
| `src/Glpi/Application/View/TemplateRenderer.php` | `prependPath('/templates.terabras')` on the Twig loader (guarded by `is_dir`) | GLPI plugins only register an `@plugin` namespace; they cannot shadow a core template's logical name. This one seam enables all UI overrides. | Low — 6 added lines next to the loader constructor; conflicts only if upstream rewrites loader setup. |
| `front/plugin.form.php` | Reject `unactivate`/`uninstall`/`clean` actions targeting the `terabras` plugin (white-label hardening; the plugin must stay always-on) | No pre-action hook exists to veto plugin lifecycle actions; must guard the controller. | Low — a self-contained guard block before the action switch; conflicts only if upstream rewrites this short controller. |
| `install/install.php` | (1) `header_html()`: page `<title>`/`<h2>` `"GLPI setup"` → `"<app_name> setup"` (reads `app_name`). (2) `step8()` (install finalize): calls `\GlpiPlugin\Terabras\Provisioning::run()` and passes the administrator credentials it returns to the step-8 template | (1) The installer runs before any plugin loads, so its own chrome can't be branded via the plugin — the text must read `app_name` directly. (2) GLPI never auto-activates plugins on install, so a fresh instance shipped un-branded **and** exposed `terabras` as an inactive/removable entry in the plugins list (the `ADD_DEFAULT_WHERE` hide-hook only runs once the plugin is active). | Low — text edits + a two-line call before `Session::destroy()`. All the logic lives in the plugin, so upstream changes to `step8()` rarely collide. The CLI path does not run `step8`, so provisioning there goes through `php bin/console plugins:terabras:postinstall` (the container entrypoint does this on every boot). |
| `css/install.scss` | Installer mini-theme recolored to the brand: body `#1b2f62`→`#0f0f38`, `#bloc` `#3a5693`→`#140078`, links/`h2` gold `#fec95c`→orange `#ff7800`; `#logo_bloc` uses `pics/logos/logo-GLPI-100-white.png` (`contain`, wider box) — the same generated mark as the rest of the product, so there is no installer-only copy to keep in sync | Same reason — plugin CSS is not loaded during install, so the installer's own SCSS carries the brand. | Low — value-only changes in a small install-only stylesheet; conflicts only if upstream restyles the installer. |
| `templates/layout/parts/user_header.html.twig` | About modal: logo `title`, version and copyright lines now read the product name (`config('app_name')` / "Terabras"); removed the upstream-version-advertising block that linked to glpi-project.org | The About modal markup is inline in a large shared header partial; shadowing the whole file via the seam would strand it from upstream changes to the user menu. | Low — 3 localized string edits + one block deletion. |
| `templates/layout/page_card_notlogged.html.twig` | Login logo tooltip `title="GLPI"` → `title="{{ config('app_name') }}"` | Same partial-shadowing tradeoff as above; a one-attribute edit is cleaner. | Low — 1 line. |
| `src/GLPIPDF.php` | PDF `Creator`/`Author` metadata `'GLPI'` → `$CFG_GLPI['app_name']` (added `global $CFG_GLPI`) | Hardcoded string in the PDF constructor; no hook. | Low — 3 lines. |
| `src/Glpi/Rules/RulesManager.php` | `initializeRules()`: the `Config::setConfigurationValues()` write moved **out** of the per-collection loop (one write instead of N) | `Config` is historized, so writing inside the loop appended one "initialized_rules_collections" row to the configuration history per rule collection — a plain install produced 9 entries under Setup → General → Historical before an administrator had touched anything. There is no hook around this method. | Low — the stored value is cumulative, so one write at the end is equivalent; a guard skips the write entirely when nothing was initialized. Worth proposing upstream. |
| `src/autoload/CFG_GLPI.php` | Default `$CFG_GLPI['app_name']` `'GLPI'` → `'Terabras'` | The plugin's `POST_INIT` override does not run during **install** or on a plugin-less instance, so the brand had to move into the default itself (fixes the raw "GLPI" on the installer/login footer, auth "internal database" label, PDF/2FA). | Low — 1 line. |
| `src/Html.php` | `getCopyrightMessage()`: hardcoded `"GLPI … Teclib' and contributors"` + glpi-project.org link → product name via `app_name`, attribution/link removed | Copyright string is built in PHP (not a template), so CSS/plugin cannot reach it on the installer/login where the plugin is not loaded. | Low — self-contained function; conflicts only if upstream rewrites the copyright markup. |

### Binary asset swaps (white-label marks)

These upstream image files are **generated** from the brand kit by
`plugins/terabras/tools/generate_logos.py` and committed. They exist because the
pages that render before any plugin can load — the setup wizard, the "database
must be configured" screen, the error pages shown when the database is
unreachable — have no way to reach a plugin asset. Everything else goes through
the `--glpi-logo-*` custom properties in `plugins/terabras/public/css/branding.css`.

Re-run the generator (never hand-edit) if the brand changes:

```
<venv>/bin/python plugins/terabras/tools/generate_logos.py
```

| Upstream file | Generated from | Notes |
|---------------|----------------|-------|
| `public/pics/logos/logo-GLPI-100-{black,grey}.png` | wordmark, navy | GLPI's `logo-GLPI-*` slot IS a wordmark |
| `public/pics/logos/logo-GLPI-100-white.png` | wordmark, white body + orange segment | also the installer mark (`css/install.scss`) |
| `public/pics/logos/logo-GLPI-250-{black,grey,white}.png` | wordmark, letterboxed to 200×110 | the anonymous pages render this through `content:` at a fixed 200×110, which stretches the image; baking the ratio in keeps it undistorted where no Terabras CSS is loaded |
| `public/pics/logos/logo-G-100-{black,grey,white}.png` | symbol, square | reduced mark (collapsed rail) |
| `public/pics/favicon.ico` | symbol | 16/32/48/64 |

**Mapping bug fixed (2026-09):** the marks had been generated from
`Simbolo azul sem fundo.png` — a flat one-colour variant — because the generator
matched kit files by substring and the filesystem happened to return that one
first. The official mark (`Simbolo sem fundo.png`) carries an **orange** segment
on the left. On top of that, the *symbol* had been mapped onto the *wordmark*
slots, which is why the "database must be configured" screen showed a bare "T".
The generator now matches kit files by exact name and fails loudly on ambiguity,
and derives the white variants by recolouring the body while keeping the orange
segment (the kit's own all-white symbol drops it).

**Removed:** `public/pics/login_logo_terabras.png`. It duplicated
`wordmark-white-login.png`; `css/install.scss` now points at
`pics/logos/logo-GLPI-100-white.png`, which carries the same mark.

## 2. Template overrides (`templates.terabras/`)

Each file here is a **copy of the upstream template** at the mirrored path, edited.
When upstream changes the original, reconcile deliberately.

| Override | Mirrors | What we changed |
|----------|---------|-----------------|
| `templates.terabras/pages/central/index.html.twig` | `templates/pages/central/index.html.twig` | Adds a branded greeting hero above `central.display()`. |
| `templates.terabras/install/step6.html.twig` | `templates/install/step6.html.twig` | Telemetry is **opt-in**: the checkbox ships unchecked (upstream renders it `checked` from `Telemetry::showTelemetry()`, silently opting every customer in). Drops the "Reference your GLPI" registration-form promo. Posts `Etape_6` instead of `Etape_5`, which skips the GLPI Network commercial step — that step displays no licence or copyright notice and nothing in the installation depends on it. The telemetry explanation itself is kept verbatim (same msgids): the data really does go to the GLPI project and the person deciding must be told. |
| `templates.terabras/install/step8.html.twig` | `templates/install/step8.html.twig` | Replaces the published default-credentials list (glpi/glpi, tech/tech, normal/normal, post-only/postonly) with the administrator credentials provisioning just issued, shown once. Final button reads the product name. |

## 2b. Local translation overrides (`plugins/terabras/locales/core/`)

`Session::loadLanguage()` loads `GLPI_LOCAL_I18N_DIR/core*/<lang>.php` as a `phparray`
catalogue on top of GLPI's own — the supported way for a downstream distribution to
add or override strings without patching `locales/*.po`. The container entrypoint
copies this directory to `files/_locales/core/` on every boot.

Two jobs:

1. **Strings the Terabras installer introduces** (the administrator-credentials
   block on the final wizard screen).
2. **White-labelling upstream copy.** Ordinary interface text where "GLPI" is just
   the name of the running application becomes *"o sistema"* — a Terabras user has
   no reason to meet another product's name in a routine message.

`pt_BR.php` carries the full classification in its header. What is deliberately
**not** overridden:

| Kept as-is | Why |
|------------|-----|
| Console command descriptions (`php bin/console …`) | operator troubleshooting surface; renaming makes tool and docs disagree |
| "GLPI Agent", "GLPI Native Inventory" | the agent is a **separate product** the customer installs; it must keep its real name |
| GLPI Network / GLPI Cloud / registration key copy | Teclib' commercial offer, not ours to re-label |
| Telemetry consent copy | the data really does go to the GLPI project; consent has to say so |
| Marketplace strings | the marketplace is disabled (§4); the strings are technical |
| Anything naming a table, column, class or file | it would stop matching reality |

Copyright and licence notices are not translations and are untouched.

`tests/terabras/check_locale_overrides.php` fails if a key stops matching an
upstream msgid (an override that silently went dead) or if a value still says
"GLPI".

**Cache:** GLPI caches each assembled language catalogue, so a newly shipped
override is invisible on an instance that has already served a page in that
language. `Provisioning::refreshTranslations()` clears **only** the translations
cache, keyed on a fingerprint of the deployed files.

## 3. Plugin (`plugins/terabras/`) — no divergence, self-contained

Branding, fonts, palette CSS and the dashboard skin. Loaded via `ADD_CSS`.
Never conflicts with upstream. Not tracked here.

Since the provisioning work it also carries the product's **post-install logic**:

| Path | Role |
|------|------|
| `src/Provisioning.php` | The one idempotent provisioning routine: activates the branding plugin and the declared plugin set, enables timezone support, applies product configuration, removes GLPI's published default credentials. Safe to run on every boot. |
| `src/Console/PostinstallCommand.php` | `php bin/console plugins:terabras:postinstall` — the CLI entry point, used by the container entrypoint and by any scripted deploy. |
| `locales/core/*.php` | Local translation overrides (see §2b). |
| `public/css/theme.css` §9 | The horizontal (top) navigation. See below. |

Both install paths converge on `Provisioning::run()`: the web wizard calls it from
`install/install.php` step 8, the CLI calls the console command.

**Horizontal navigation (theme.css §9).** GLPI can move the main menu out of the
navy sidebar and into the header (Setup → My settings → "Page layout"; the
self-service helpdesk is *always* in this mode). The header then carries `.topbar`
and white `--glpi-mainmenu-fg` text, but the theme's §4 painted every
`.page > header.navbar` with the light surface — so every module label rendered
white-on-white and the menu looked empty although the items were present and
clickable. §9 restores the brand navy on `body.central` only (the helpdesk's white
header is the approved design) and re-lights the user chip, icon buttons and
toggler that §4 had styled for a light bar. It also fits the six modules into the
1200–1450px band, where the bar used to overflow and clip the user chip — first by
tightening the items and swapping the wordmark for the reduced mark, then by
wrapping rather than overflowing (an overflow container would clip the dropdowns).
Covered by `tests/terabras/nav_layout.mjs`.

## 4. Downstream distribution config (`inc/downstream.php`)

GLPI's own seam for a redistributor to pin product constants — included by
`\Glpi\Application\SystemConfigurator` after environment variables and
`config/local_define.php`, with its `define()`s winning over GLPI's defaults.
It is gitignored upstream; Terabras **is** the downstream distribution, so
`.gitignore` re-includes it (`!/inc/downstream.php`).

Currently: `GLPI_MARKETPLACE_ENABLE = 0`. The marketplace needs a GLPI Network
registration key we do not ship, would let an administrator install unvetted code
into a container filesystem that is recreated on every deploy, and is the source of
the unanswered "Switch to marketplace?" prompt on Setup → Plugins. The DB-side half
of that decision (`marketplace_replace_plugins = MP_REPLACE_NEVER`) is applied by
provisioning, because `View::showFeatureSwitchDialog()` is not gated on
`Controller::isWebAllowed()`.

## 5. Packaging (`.docker/terabras/`, `docker-compose.terabras.yaml`)

`docker-compose.yaml` and `.docker/app/` are upstream GLPI's **development**
environment and stay untouched. The product stack is separate:

```
docker compose -f docker-compose.terabras.yaml up -d --build
```

| File | Role |
|------|------|
| `.docker/terabras/Dockerfile` | Product image: PHP limits from the environment, `GLPI_CONFIG_DIR`/`GLPI_VAR_DIR` off the app tree, `GLPI_ENVIRONMENT_TYPE=production`, and removal of the base image's development vhost. |
| `.docker/terabras/entrypoint.sh` | Renders the PHP ini and the Apache vhost from the environment, deploys the local i18n catalogue, precompiles the stylesheets when they changed, and runs provisioning — idempotent, on every boot. Deliberately does **not** run database migrations: upgrades stay an explicit operator step. |
| `.docker/terabras/initdb.sql` | Grants the application user `SELECT` on `mysql.time_zone*`. Without it GLPI's `DbTimezones` check fails at install time and freezes `use_timezones = false` into `config_db.php`. |
| `docker-compose.terabras.yaml` | Named volumes for `/var/glpi/config` and `/var/glpi/files`; upload limits and administrator credentials as environment variables. |
| `.dockerignore` | Keeps `.git` and any instance state out of the image. `GLPI_INSTALL_MODE` is derived from `is_dir(.git)`, so a shipped repository makes Setup → General report the product as a GIT checkout. |

**Persistence contract.** `config_db.php`, `glpicrypt.key` and the OAuth key pair live
on the `glpi_config` volume; documents, cache, sessions and logs on `glpi_files`.
Nothing that says "this instance is installed" is stored inside the application tree,
so rebuilding or replacing the app image cannot send an installed instance back to the
"GLPI must be configured" screen. `GLPI_CONFIG_DIR` must reach **both** SAPIs: the
entrypoint publishes it as an Apache `SetEnv` inside the vhost, because the base image's
own vhost pins `GLPI_ENVIRONMENT_TYPE=development` and a vhost-level `SetEnv` beats a
server-level one.

### Deployments without shell access

Some customers are on shared hosting with nothing but FTP / a file manager —
no composer, no npm, no console. `plugins/terabras/tools/build-deploy-package.sh`
produces an archive that only has to be uploaded:

```
plugins/terabras/tools/build-deploy-package.sh            # builds, then packages
plugins/terabras/tools/build-deploy-package.sh --skip-build   # package the tree as-is
```

It exists because **a `git clone` is not deployable**: `.gitignore` excludes
`vendor/`, `public/lib/`, `public/build/`, `public/css_compiled/` and
`locales/*.mo`, so an uploaded checkout dies on
`require vendor/autoload.php` — and, once that is fixed, serves an unstyled UI
with every string in English.

| Normally a console step | In the package |
|---|---|
| `composer install` | baked in, **re-dumped `--no-dev`** |
| `npm run build` | baked in |
| `tools:locales:compile` | baked in (`locales/*.mo`) |
| `build:compile_scss` | baked in (`public/css_compiled/`) |
| `database:install` | the web wizard |
| `plugins:terabras:postinstall` | already runs inside the wizard's final step |
| `php.ini` | `public/.user.ini`, plus a `.htaccess` variant for mod_php |
| cron | the host's scheduler, or GLPI's internal mode — nothing can bake this in |

Three traps the script guards against, each found by actually installing the
archive on a PHP 8.2 host with no shell:

1. **A dev autoloader.** A working tree is installed *with* dev dependencies, and
   that autoloader eagerly requires `tests/src/autoload/functions.php` — a path
   the package deliberately excludes. The script re-dumps `--no-dev` and then
   refuses to ship if any eagerly-required file is missing.
2. **Translations arriving too late.** Provisioning copies the catalogues, but on
   this deployment it only runs in the wizard's *final* step — the same request
   that already loaded the translator, so that last screen rendered in English.
   The package pre-deploys them to `files/_locales/core/`.
3. **PHP limits applied after the fact.** GLPI records `document_max_size` from
   whatever PHP reports *at install time*, and here provisioning never runs
   again to correct it. The printed instructions put the limits before the
   wizard, and call out that `.user.ini` is ignored under mod_php.

The administrator login default (`admin`) therefore lives in
`Provisioning::DEFAULT_ADMIN_LOGIN`, not only in the compose file: this
deployment has no environment to read.

### Environment

| Variable | Default | Meaning |
|----------|---------|---------|
| `GLPI_CONFIG_DIR` / `GLPI_VAR_DIR` | `/var/glpi/config`, `/var/glpi/files` | Volume mount points. Changing these changes where "installed" lives. |
| `PHP_UPLOAD_MAX_FILESIZE` / `PHP_POST_MAX_SIZE` | `128M` / `144M` | The stack's real upload ceiling. Provisioning realigns GLPI's `document_max_size` to `min(upload, post)` on every boot, so the UI can never advertise more than PHP accepts. |
| `PHP_MEMORY_LIMIT` | `512M` | |
| `TERABRAS_ADMIN_USER` | `admin` | Administrator login. Not GLPI's factory `glpi`: the home page greets the user by this name, so leaving it would put another product's name on the dashboard. Provisioning renames the account only while it still carries the factory password, so an instance whose administrator has already set one is never touched. |
| `TERABRAS_ADMIN_PASSWORD` | *(unset)* | Administrator password. Unset ⇒ a strong one is generated and shown **once** (final wizard screen, or the entrypoint log for a CLI install). |
| `TERABRAS_PLUGINS` | *(empty)* | Comma-separated third-party plugin directories to install and activate. Empty means branding only. Activating one runs that plugin's own installer, so the set is declared, never inferred from what happens to be in `plugins/`. Pinned versions: `plugins/terabras/PLUGINS.lock`. |
| `TERABRAS_PUBLIC_URL` | *(empty)* | The URL users reach. **Set it on any real deployment.** The installer otherwise derives `url_base` from the `Referer` of the last wizard request, which a reverse proxy rewrites — and `url_base` drives every generated link, every notification e-mail and the cookie-secure decision below. |
| `PHP_SESSION_COOKIE_SECURE` | `auto` | `auto` resolves it from `url_base` on every boot, so moving the site behind TLS clears the "session.cookie_secure should be on" warning by itself instead of leaving a stale one. Force with `0`/`1` when TLS terminates somewhere the application cannot see. `session.cookie_samesite=Lax` is set unconditionally. |
| `TERABRAS_MIGRATE_FORMCREATOR` | *(off)* | Runs the one-time Formcreator → core forms migration. Off by default: provisioning runs on **every** boot, and that is the wrong moment to rewrite customer data unattended. While data is pending and the flag is off, provisioning says so in the log. |

### Tests

| Test | Covers |
|------|--------|
| `tests/terabras/install_flow.sh` | Drives the real wizard over HTTP against a wiped stack, asserts the post-install state, then recreates the containers and asserts the instance is still installed. |
| `tests/terabras/nav_layout.mjs` | Both navigation layouts: module visibility, label contrast, dropdowns, header overflow, at 1920/1600/1366/1280/1024 px and 100/125/150 % zoom. |
| `tests/terabras/branding.mjs` | The mark shown on each screen, and that ordinary copy no longer exposes "GLPI" (with an allow-list that documents every surviving mention). |
| `tests/terabras/check_locale_overrides.php` | Every translation override still matches an upstream msgid, and no override value still says "GLPI". |

The browser tests need the stack up and Playwright's chromium (`node_modules`).
Run them after any change to the installer, the provisioning routine, the theme or
the packaging.

---

## Production hardening (SaaS deploy checklist)

Most of this list is now **handled by the product stack** (§5) rather than by a runbook.
What remains:

- **`GLPI_ENVIRONMENT_TYPE=production`** — hides PHP backtraces on error pages, disables
  the Symfony debug toolbar, enables prod caching. *Set by `.docker/terabras/Dockerfile`
  and published to Apache by the entrypoint; the base image's own vhost pins
  `development`, which is why the entrypoint replaces that vhost.*
- **Provisioning after install** — *done: the entrypoint runs
  `plugins:terabras:postinstall` on every boot, and the web wizard calls the same
  routine from step 8.*
- **Upload limits** — *done: `PHP_UPLOAD_MAX_FILESIZE` / `PHP_POST_MAX_SIZE`, with GLPI's
  `document_max_size` realigned to them.*
- **Timezone support** — *done: the DB init grants `SELECT` on `mysql.time_zone*`, and
  provisioning re-evaluates `use_timezones` on every boot.*
- **Precompiled stylesheets** — *done: the entrypoint runs `build:compile_scss` when the
  SCSS fingerprint changes, writing `public/css_compiled/`, which GLPI serves in
  preference to the runtime compiler.* This also fixes a subtler problem: GLPI keys its
  runtime SCSS cache on the GLPI **version**, not on stylesheet contents, and that cache
  lives on the data volume — so before this, shipping restyled CSS without bumping the
  GLPI version had no effect at all. In an image that COPYs the source, move the compile
  to build time (see the note in `.docker/terabras/Dockerfile`).
- **Vendor the pinned third-party plugins** listed in `plugins/terabras/PLUGINS.lock`
  into the image (`plugins/*` except `terabras*` is gitignored), then declare the ones
  the edition ships in `TERABRAS_PLUGINS`. *(still a pipeline task)*
- **Base the release on a tagged GLPI version.** The current base is `11.0.9-dev`, so
  Setup → General → System shows *"This version is UNSTABLE and some SECURITY FIXES may
  not be included"*. That banner is accurate, not a bug — clearing it means rebasing
  onto a released tag, which is a release decision. *(still open)*
- **Reverse proxy body limit.** The app container accepts 128 MB uploads; any proxy in
  front of it needs a matching limit (`client_max_body_size` on nginx, `LimitRequestBody`
  on Apache, the ingress annotation on Kubernetes). Nothing inside this repo can enforce
  that. *(deployment-specific)*

## How to pull an upstream security fix

```
git fetch upstream
git log --oneline upstream/11.0/bugfixes ^HEAD    # find the fix commit
git cherry-pick <sha>
```
Conflicts will almost always be in `src/`, `front/`, `ajax/` (PHP) — which we barely
touch (only the one file in §1). Presentation lives in `templates.terabras/` and the
plugin, so it rarely collides.
