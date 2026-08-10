# Terabras — GLPI branding plugin

White-labels this GLPI instance as **Terabras** without editing any GLPI core
file, so upstream GLPI updates keep merging cleanly.

## What it changes

| Area | How |
|------|-----|
| Product name (page titles, e-mails, 2FA issuer) | `CFG_GLPI['app_name'] = 'Terabras'` via the `POST_INIT` hook |
| Header / side-menu logo | `--glpi-logo-*` CSS variables → Terabras wordmark & symbol |
| Login screen logo | same, larger login variants |
| Brand colors (light **and** dark) | `--tblr-primary`, `--glpi-mainmenu-*`, `--glpi-palette-color-*` |
| GLPI "Powered by Teclib" copyright | hidden + replaced with "© Terabras" via CSS |
| GLPI logo in the *About* dialog | replaced with the Terabras symbol via CSS |
| **Brand typeface — Kanit** (the official Terabras font, self-hosted) | `fonts.css` `@font-face` + `--tblr-font-sans-serif` |
| **Brand-faithful theme** (navy chrome + orange accent, angular geometry, Kanit type, light + dark) | `theme.css`, via Tabler `--tblr-*` + GLPI `--glpi-*` token overrides |

Everything here follows the official identity manual (Renato AB Studio, 2025),
not a generic interpretation:

- **Typeface:** Kanit (Light→Bold), self-hosted & Latin-subset in `public/fonts/`.
- **Palette:** navy `#140078` (Pantone 2738 C) dominant, orange `#FF7800`
  (Pantone 151 C) as the single accent; near-black navy `#0F0F38` for deep
  surfaces; greys `#E6E6EA` / `#C4C4C4`; white.
- **Geometry:** angular — small radii, right angles, orange accent-marks (the
  active side-menu item gets the brand's orange bar). No soft/pill shapes.
- **Chrome:** navy navigation region + white content + orange emphasis, mirroring
  the brand's own website/app mockups.
- **Register:** premium SaaS ("Stripe / Vercel") — layered elevation, subtle
  navy gradients, gradient-depth buttons, and KPI tiles with an orange highlight
  stripe. Confident use of the brand colours for emphasis, not a flat minimal look.

Three stylesheets load in order: `fonts.css` (Kanit) → `branding.css` (identity:
logos/colors/name) → `theme.css` (typography, geometry, chrome). None touch core.

## Files

```
terabras/
├── setup.php                 # hooks: app_name + CSS injection
├── public/
│   ├── css/fonts.css         # Kanit @font-face (self-hosted)
│   ├── css/branding.css      # logos + colors + login/footer
│   ├── css/theme.css         # brand theme (Kanit, navy/orange, angular, dark)
│   ├── fonts/                # Kanit *.woff2 (Latin subset) + OFL licence
│   └── img/                  # generated logo assets (committed)
└── tools/
    ├── generate_logos.py     # regenerates public/img/ from the brand kit
    └── generate_fonts.sh     # regenerates public/fonts/ from Kanit.zip
```

## Enable

```bash
php bin/console plugin:install terabras
php bin/console plugin:activate terabras
```
or via **Setup → Plugins** in the UI.

Light vs dark is chosen by the user with the normal GLPI theme switch
(**My settings → Palette**, or **Setup → General** for the default). The
Terabras colors apply on top of GLPI's light theme and its dark-family themes
(`dark`, `darker`, `midnight`).

## Regenerating logos

Logo assets are generated from the brand identity kit
(`TERABRAS_IdentidadeVisual*/`, kept out of git). If the brand changes, restore
the kit at the repo root and run:

```bash
python plugins/terabras/tools/generate_logos.py   # needs Pillow
```

## Upstream updates

This plugin is the only place Terabras customizations live, so pulling GLPI
upstream is a normal merge:

```bash
git fetch upstream
git merge upstream/11.0/bugfixes   # or the target GLPI branch/tag
```

There should be no conflicts inside `plugins/terabras/`. If a future GLPI
version renames a `--glpi-logo-*` variable or the `.copyright` markup, only
`branding.css` needs a touch-up.
