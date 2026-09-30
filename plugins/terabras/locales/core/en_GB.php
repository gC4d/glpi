<?php

/**
 * Terabras — local translation overrides for the GLPI core catalogue (en_GB).
 *
 * The English counterpart of pt_BR.php — see that file for the full rationale on
 * what is white-labelled and what is deliberately left carrying the GLPI name
 * (console command help, the GLPI Agent, GLPI Network, telemetry consent copy,
 * marketplace, and anything naming a table, column, class or file).
 *
 * The array KEYS are upstream msgids and must stay exactly as upstream writes
 * them; only the values are ours. tests/terabras/check_locale_overrides.php
 * fails the build if a key stops matching an upstream msgid.
 */

return [
    // Strings introduced by the Terabras installer (templates.terabras/install/).
    'Administrator account'
        => 'Administrator account',
    'This password is shown only once. Copy it now and store it somewhere safe.'
        => 'This password is shown only once. Copy it now and store it somewhere safe.',
    'The administrator account %s was configured with the password provided to the installer.'
        => 'The administrator account %s was configured with the password provided to the installer.',
    'You can create, modify or delete accounts once logged in.'
        => 'You can create, modify or delete accounts once logged in.',
    'Use %s'
        => 'Use %s',
    '%s internal database'
        => '%s internal database',

    'GLPI'
        => 'Terabras',
    'Use GLPI'
        => 'Use Terabras',
    'Use GLPI in mode'
        => 'Use the system in mode',
    'You have at least one automatic action configured in GLPI mode, we advise you to switch to CLI mode.'
        => 'You have at least one automatic action configured in Terabras mode, we advise you to switch to CLI mode.',
    'GLPI setup'
        => 'System setup',
    'GLPI update'
        => 'System update',
    'Installation or update of GLPI'
        => 'Installation or update of the system',
    'The GLPI database must be configured and installed.'
        => 'The system database must be configured and installed.',
    'If you see this page when the installation has already been done, it means that the GLPI database configuration file has been removed or corrupted.'
        => 'If you see this page when the installation has already been done, it means that the system database configuration file has been removed or corrupted.',
    "Choose 'Install' for a completely new installation of GLPI."
        => "Choose 'Install' for a completely new installation of the system.",
    "Select 'Upgrade' to update your version of GLPI from an earlier version"
        => "Select 'Upgrade' to update your version of the system from an earlier version",
    'Checking of the compatibility of your environment with the execution of GLPI'
        => 'Checking of the compatibility of your environment with the execution of the system',
    'Caution! You will update the GLPI database named: %s'
        => 'Caution! You will update the system database named: %s',
    'Current GLPI version not found for database named "%s". Update cannot be done.'
        => 'Current system version not found for database named "%s". Update cannot be done.',
    'The GLPI codebase has been updated. The update of the GLPI database is necessary.'
        => 'The system codebase has been updated. The update of the system database is necessary.',
    'You are trying to use GLPI with outdated files compared to the version of the database. Please install the correct GLPI files corresponding to the version of your database.'
        => 'You are trying to use the system with outdated files compared to the version of the database. Please install the correct files corresponding to the version of your database.',
    'The database contains no GLPI tables.'
        => 'The database contains no system tables.',
    'Unable to load the GLPI configuration from the database.'
        => 'Unable to load the system configuration from the database.',
    'Unable to fetch GLPI version.'
        => 'Unable to fetch the system version.',
    'PHP directive "session.cookie_secure" should be set to "on" when GLPI can be accessed on HTTPS protocol.'
        => 'PHP directive "session.cookie_secure" should be set to "on" when the system can be accessed on HTTPS protocol.',
    'The web server is configured to allow session cookies only on secured context (https). Therefore, you must access GLPI on a secured context to be able to use it.'
        => 'The web server is configured to allow session cookies only on secured context (https). Therefore, you must access the system on a secured context to be able to use it.',
    'A minimum of %s is commonly required for GLPI.'
        => 'A minimum of %s is commonly required for the system.',
    'A minimum of 64 Mio is commonly required for GLPI.'
        => 'A minimum of 64 Mio is commonly required for the system.',
    'Installing and enabling the "%s" extension may improve GLPI performance'
        => 'Installing and enabling the "%s" extension may improve system performance',
    'Even if GLPI still supports this PHP version, an upgrade to a more recent PHP version is recommended.'
        => 'Even if the system still supports this PHP version, an upgrade to a more recent PHP version is recommended.',
    'Enable usage of ChaCha20-Poly1305 encryption required by GLPI. This is provided by libsodium 1.0.12 and newer.'
        => 'Enable usage of ChaCha20-Poly1305 encryption required by the system. This is provided by libsodium 1.0.12 and newer.',
    'Permissions for GLPI data directories'
        => 'Permissions for the system data directories',
    'The database schema is not consistent with the current GLPI version.'
        => 'The database schema is not consistent with the current system version.',
    'The database schema is not consistent with the installed GLPI version (%s).'
        => 'The database schema is not consistent with the installed system version (%s).',
    'It is recommended to run the "%s" command to validate that the database schema is consistent with the current GLPI version.'
        => 'It is recommended to run the "%s" command to validate that the database schema is consistent with the current system version.',
    'GLPI source code integrity is validated.'
        => 'the system source code integrity is validated.',
    'GLPI source code integrity is not validated.'
        => 'the system source code integrity is not validated.',
    'GLPI version'
        => 'System version',
    'GLPI database version'
        => 'System database version',
    'Default language of GLPI'
        => 'Default language of the system',
    'GLPI server time zone'
        => 'Server time zone',
    'GLPI history delay:'
        => 'History delay:',
    'This indicates the delay between the source and the replica for GLPI history (table: glpi_logs, column: date_mod).'
        => 'This indicates the delay between the source and the replica for the system history (table: glpi_logs, column: date_mod).',
    'The maximum upload size is primarily determined by the "upload_max_filesize" and "post_max_size" PHP settings. The setting below is to further restrict the uploads for just GLPI.'
        => 'The maximum upload size is primarily determined by the "upload_max_filesize" and "post_max_size" PHP settings. The setting below is to further restrict the uploads within the system only.',
    'Cache namespace can be use to ensure either separation or sharing of multiple GLPI instances data on same cache system.'
        => 'Cache namespace can be use to ensure either separation or sharing of multiple system instances data on same cache system.',
    'Show GLPI ID'
        => 'Show system ID',
    'Do not use this option if your GLPI is reachable from the internet. Use at your own risk.'
        => 'Do not use this option if the system is reachable from the internet. Use at your own risk.',
    'For more information, check the GLPI logs.'
        => 'For more information, check the system logs.',
    'URL "%s" is not considered safe and cannot be fetched from GLPI server.'
        => 'URL "%s" is not considered safe and cannot be fetched from the system server.',
    'Authentication on GLPI database'
        => 'Authentication on the system database',
    'User not authorized to connect in GLPI'
        => 'User not authorized to connect to the system',
    '%s wants to access your GLPI account'
        => '%s wants to access your system account',
    'If the given email address corresponds to one and only one GLPI user, you will receive an email containing the information required to reset your password. Please contact your administrator if you do not receive an email.'
        => 'If the given email address corresponds to one and only one system user, you will receive an email containing the information required to reset your password. Please contact your administrator if you do not receive an email.',
    'Contact your GLPI admin!'
        => 'Contact your system administrator!',
    'Go back to GLPI'
        => 'Go back to the system',
    'GLPI page'
        => 'System page',
    'Automatically generated by GLPI %s'
        => 'Automatically generated by the system %s',
    'Last update in GLPI'
        => 'Last update in the system',
    'The asset is created or updated in GLPI'
        => 'The asset is created or updated in the system',
    'Software deleted by GLPI dictionary rules'
        => 'Software deleted by dictionary rules',
    '%s "%s" (%d) is most recent on GLPI side, its update has been skipped.'
        => '%s "%s" (%d) is most recent on the system side, its update has been skipped.',
    'Other items do not exist in GLPI core.'
        => 'Other items do not exist in the system core.',
    'This will only work for types from GLPI itself or enabled plugins that support this action.'
        => 'This will only work for types from the system itself or enabled plugins that support this action.',
    'It can be personalized, but some words are reserved such as classes from GLPI like Computer, Monitor, etc.'
        => 'It can be personalized, but some words are reserved such as system classes like Computer, Monitor, etc.',
    'The object types families have not been imported as they are not handled by GLPI.'
        => 'The object types families have not been imported as they are not handled by the system.',
    'CRA is mandatory due to GLPI configuration'
        => 'CRA is mandatory due to the system configuration',
    'Challenge–response authentication can be used to validate the identity of the target server before any data is sent from GLPI. This uses the shared secret that was generated in the above section.'
        => 'Challenge–response authentication can be used to validate the identity of the target server before any data is sent by the system. This uses the shared secret that was generated in the above section.',
    'The webhook secret can be shared with a target server to allow it to validate requests are actually coming from the GLPI server and that the content was not modified between GLPI and the target.'
        => 'The webhook secret can be shared with a target server to allow it to validate requests are actually coming from the system server and that the content was not modified between the system and the target.',
    'This plugin is not available for your GLPI version.'
        => 'This plugin is not available for your system version.',
    'Plugin "%s" is not available for your GLPI version.'
        => 'Plugin "%s" is not available for your system version.',
    'This plugin requires GLPI parameter %1$s'
        => 'This plugin requires system parameter %1$s',
    'Racks plugin is not part of GLPI plugin list. It has never been installed or has been cleaned.'
        => 'Racks plugin is not part of the system plugin list. It has never been installed or has been cleaned.',
    'You are about to launch migration of Appliances plugin data into GLPI core tables.'
        => 'You are about to launch migration of Appliances plugin data into the system core tables.',
    'You are about to launch migration of Racks plugin data into GLPI core tables.'
        => 'You are about to launch migration of Racks plugin data into the system core tables.',
    'You are about to launch migration of "%s" plugin data into GLPI core tables.'
        => 'You are about to launch migration of "%s" plugin data into the system core tables.',
];
