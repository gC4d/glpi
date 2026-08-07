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

    // Stylesheets, in order: branding.css (identity: logos/colors/name) then
    // theme.css (the modern reskin, which owns the chrome look).
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['terabras']                = ['css/branding.css', 'css/theme.css'];
    $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['terabras'] = ['css/branding.css', 'css/theme.css'];

    // Product name — retitles pages, notification e-mails and the 2FA issuer.
    $PLUGIN_HOOKS[Hooks::POST_INIT]['terabras'] = 'plugin_terabras_postinit';
}

/**
 * Runs after all plugins have initialised, once core config is loaded.
 */
function plugin_terabras_postinit(): void
{
    global $CFG_GLPI;

    $CFG_GLPI['app_name'] = 'Terabras';
}
