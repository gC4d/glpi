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
| `templates/layout/parts/user_header.html.twig` | About modal: logo `title`, version and copyright lines now read the product name (`config('app_name')` / "Terabras"); removed the upstream-version-advertising block that linked to glpi-project.org | The About modal markup is inline in a large shared header partial; shadowing the whole file via the seam would strand it from upstream changes to the user menu. | Low — 3 localized string edits + one block deletion. |
| `templates/layout/page_card_notlogged.html.twig` | Login logo tooltip `title="GLPI"` → `title="{{ config('app_name') }}"` | Same partial-shadowing tradeoff as above; a one-attribute edit is cleaner. | Low — 1 line. |
| `src/GLPIPDF.php` | PDF `Creator`/`Author` metadata `'GLPI'` → `$CFG_GLPI['app_name']` (added `global $CFG_GLPI`) | Hardcoded string in the PDF constructor; no hook. | Low — 3 lines. |

### Binary asset swaps (white-label logos/favicon)

These core image files were overwritten with the Terabras marks (they are `<img src>` / favicon refs that CSS cannot reach). Restore from upstream if a merge touches them.

| File | Replaced with |
|------|---------------|
| `public/pics/favicon.ico` | `plugins/terabras/public/img/favicon.ico` |
| `public/pics/logos/logo-GLPI-{100,250}-{grey,black}.png` | `plugins/terabras/public/img/symbol-blue.png` |
| `public/pics/logos/logo-GLPI-{100,250}-white.png` | `plugins/terabras/public/img/symbol-white.png` |

## 2. Template overrides (`templates.terabras/`)

Each file here is a **copy of the upstream template** at the mirrored path, edited.
When upstream changes the original, reconcile deliberately.

| Override | Mirrors | What we changed |
|----------|---------|-----------------|
| `templates.terabras/pages/central/index.html.twig` | `templates/pages/central/index.html.twig` | Adds a branded greeting hero above `central.display()`. |

## 3. Plugin (`plugins/terabras/`) — no divergence, self-contained

Branding, fonts, palette CSS and now the dashboard skin. Loaded via `ADD_CSS`.
Never conflicts with upstream. Not tracked here.

---

## How to pull an upstream security fix

```
git fetch upstream
git log --oneline upstream/11.0/bugfixes ^HEAD    # find the fix commit
git cherry-pick <sha>
```
Conflicts will almost always be in `src/`, `front/`, `ajax/` (PHP) — which we barely
touch (only the one file in §1). Presentation lives in `templates.terabras/` and the
plugin, so it rarely collides.
