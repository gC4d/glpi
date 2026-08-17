<?php

/**
 * Terabras — branding plugin for GLPI.
 *
 * Self-contained white-label layer: application name, logos, brand colors
 * (light + dark) and login/footer tweaks. It touches NO GLPI core file, so
 * upstream GLPI updates keep merging cleanly. Everything is driven through the
 * official plugin hooks:
 *   - POST_INIT               -> overrides CFG_GLPI['app_name']
 *   - ADD_CSS                 -> injects branding.css on authenticated pages
 *   - ADD_CSS_ANONYMOUS_PAGE  -> injects branding.css on the login/anonymous pages
 *
 * Brand: blue #140078 / orange #FF7800.
 */

use Glpi\Plugin\Hooks;

const PLUGIN_TERABRAS_VERSION = '1.0.0';

/**
 * Metadata read by GLPI's plugin manager.
 */
function plugin_version_terabras()
{
    return [
        'name'         => 'Terabras',
        'version'      => PLUGIN_TERABRAS_VERSION,
        'author'       => 'Terabras',
        'license'      => 'GPL v3+',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '12.0.0',
            ],
        ],
    ];
}

/**
 * No prerequisites beyond a supported GLPI version.
 */
function plugin_terabras_check_prerequisites(): bool
{
    return true;
}

function plugin_terabras_check_config(): bool
{
    return true;
}

function plugin_terabras_install(): bool
{
    return true;
}

function plugin_terabras_uninstall(): bool
{
    return true;
}

/**
 * Registered hooks. Called on every page load.
 */
function plugin_init_terabras(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['terabras'] = true;

    // Stylesheets, in order: fonts.css (Kanit @font-face) → branding.css (identity)
    // → theme.css (tokens/chrome) → components.css (component detail) → dashboard.css.
    //
    // GLPI cache-busts plugin assets with the plugin *version* (fixed), so edits to
    // these files wouldn't reach browsers until a version bump / hard-refresh. We append
    // our own `?r=<max mtime>` so every CSS edit changes the URL and browsers refetch.
    $css_files = ['fonts', 'branding', 'theme', 'components', 'dashboard'];
    $css_bust  = 0;
    foreach ($css_files as $f) {
        $mtime = @filemtime(__DIR__ . '/public/css/' . $f . '.css');
        if ($mtime !== false && $mtime > $css_bust) {
            $css_bust = $mtime;
        }
    }
    $q = '?r=' . $css_bust;
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['terabras']                = ['css/fonts.css' . $q, 'css/branding.css' . $q, 'css/theme.css' . $q, 'css/components.css' . $q, 'css/dashboard.css' . $q];
    $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['terabras'] = ['css/fonts.css' . $q, 'css/branding.css' . $q, 'css/theme.css' . $q];

    // Plugin JS: dark-mode toggle first (applies early to cut flash), then the
    // ECharts restyling (dashboard charts are canvas, so JS not CSS).
    $dm_mtime = @filemtime(__DIR__ . '/public/js/darkmode.js') ?: 0;
    $ux_mtime = @filemtime(__DIR__ . '/public/js/ux.js') ?: 0;
    $js_mtime = @filemtime(__DIR__ . '/public/js/charts.js') ?: 0;
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['terabras'] = [
        'js/darkmode.js?r=' . $dm_mtime,
        'js/ux.js?r=' . $ux_mtime,
        'js/charts.js?r=' . $js_mtime,
    ];

    // Product name — retitles pages, notification e-mails and the 2FA issuer.
    $PLUGIN_HOOKS[Hooks::POST_INIT]['terabras'] = 'plugin_terabras_postinit';

    // White-label hardening: hide Terabras from the plugins management list so
    // the branding layer isn't visible as a removable plugin. (Uninstall/disable
    // is additionally blocked server-side in front/plugin.form.php.)
    $PLUGIN_HOOKS[Hooks::ADD_DEFAULT_WHERE]['terabras'] = 'plugin_terabras_add_default_where';
}

/**
 * Exclude the Terabras plugin from the Plugin search list (white-label).
 * Receives [$itemtype, $criteria] from the add_default_where hook.
 */
function plugin_terabras_add_default_where($params)
{
    if (is_array($params) && ($params[0] ?? null) === 'Plugin') {
        $params[1][] = new \Glpi\DBAL\QueryExpression("`glpi_plugins`.`directory` <> 'terabras'");
    }
    return $params;
}

/**
 * Runs after all plugins have initialised, once core config is loaded.
 */
function plugin_terabras_postinit(): void
{
    global $CFG_GLPI;

    $CFG_GLPI['app_name'] = 'Terabras';
}
