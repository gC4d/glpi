<?php

/**
 * Terabras — downstream distribution configuration.
 *
 * GLPI's official seam for a redistributor to pin product-level constants
 * (see \Glpi\Application\SystemConfigurator: this file is included after
 * environment variables and `config/local_define.php`, and its `define()` calls
 * win over GLPI's defaults).
 *
 * Using it keeps these decisions out of the GLPI core tree AND out of the
 * per-deployment volume, so every Terabras instance boots with them.
 */

// ---------------------------------------------------------------------------
// Plugin marketplace
// ---------------------------------------------------------------------------
// Terabras ships a curated, version-pinned plugin set (plugins/terabras/PLUGINS.lock)
// baked into the image. The GLPI Network marketplace is therefore not part of the
// product: it needs a GLPI Network registration key we do not ship, it would let an
// administrator install unvetted code into a container filesystem that is recreated
// on every deploy, and it is the source of the unanswered "Switch to marketplace?"
// prompt on Setup > Plugins.
//
// 0 = completely disabled, 1 = CLI only, 2 = Web only, 3 = CLI and Web.
if (!defined('GLPI_MARKETPLACE_ENABLE')) {
    define('GLPI_MARKETPLACE_ENABLE', 0);
}
