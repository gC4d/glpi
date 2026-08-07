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
| **Modern reskin** (light airy side menu, softer radii/borders/shadows, refined cards/tables/buttons, light + dark) | `theme.css`, via Tabler `--tblr-*` + GLPI `--glpi-*` token overrides |

Brand palette: blue `#140078` (Pantone 2738 C) / orange `#FF7800` (Pantone 151 C).
Design direction: clean modern SaaS ("Linear/Notion").

Two stylesheets load in order: `branding.css` (identity) then `theme.css` (the
reskin, which owns the chrome look). Neither touches core.

## Files

```
terabras/
├── setup.php                 # hooks: app_name + CSS injection
├── public/
│   ├── css/branding.css      # logos + colors + login/footer
│   ├── css/theme.css         # modern reskin (chrome, cards, tables, dark)
│   └── img/                  # generated logo assets (committed)
└── tools/generate_logos.py   # regenerates public/img/ from the brand kit
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
