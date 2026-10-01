<?php

/**
 * Terabras — post-install provisioning.
 *
 * Every step here is **idempotent**: the class is the single entry point used by
 * both install paths (the web wizard's final step and the CLI
 * `plugins:terabras:postinstall` command) and it is safe to run on every
 * container start.
 *
 * It exists because a stock GLPI install leaves the instance in a state that is
 * not acceptable for a shipped product:
 *   - `use_timezones` is frozen to `false` in `config/config_db.php` whenever the
 *     DB's timezone tables were not readable at the exact moment step 4 ran;
 *   - the plugin marketplace asks the end user an unanswered question on the
 *     plugins page;
 *   - `document_max_size` is seeded from PHP's `upload_max_filesize` and then
 *     never reconciled when the PHP limits change;
 *   - the well-known glpi/glpi, tech/tech, normal/normal and post-only/postonly
 *     accounts stay active with their published passwords.
 *
 * @see TERABRAS_DIVERGENCE.md
 */

namespace GlpiPlugin\Terabras;

use Auth;
use Config;
use DBConnection;
use Glpi\Cache\CacheManager;
use Glpi\Form\AccessControl\FormAccessControlManager;
use Glpi\Form\Migration\FormMigration;
use Glpi\Marketplace\Controller as MarketplaceController;
use Glpi\System\Requirement\DbTimezones;
use Plugin;
use Toolbox;
use User;

final class Provisioning
{
    /** Default GLPI accounts that must never ship active with their published password. */
    public const DEFAULT_ACCOUNTS = ['tech', 'normal', 'post-only'];

    /** The administrator account GLPI creates at install time. */
    public const FACTORY_ACCOUNT = 'glpi';

    /**
     * What that account is renamed to when `TERABRAS_ADMIN_USER` is not set.
     *
     * The login is shown to the user — the home page greets them by it — so leaving
     * GLPI's factory name would put another product's name on the dashboard. This
     * default lives HERE, not only in docker-compose.terabras.yaml, because a
     * deployment without a container (shared hosting installed through the web
     * wizard) has no environment to read.
     */
    public const DEFAULT_ADMIN_LOGIN = 'admin';

    /**
     * Run every provisioning step.
     *
     * @return array{
     *     messages: list<string>,
     *     admin: array{name: string, password: ?string}|null
     * } `admin.password` is non-null only when this run actually set it, so the
     *   caller can display it exactly once.
     */
    public static function run(): array
    {
        $messages = [];

        $messages[] = self::activateBrandingPlugin();
        $messages[] = self::activateDeclaredPlugins();
        $messages[] = self::enableTimezones();
        $messages[] = self::applyProductConfig();
        $messages[] = self::refreshTranslations();
        $messages[] = self::migrateFormcreator();

        $admin = self::hardenDefaultAccounts($messages);

        return [
            'messages' => array_values(array_filter($messages)),
            'admin'    => $admin,
        ];
    }

    /**
     * GLPI never auto-activates plugins, so a fresh instance would ship un-branded
     * and expose `terabras` as an inactive (removable) entry in the plugins list.
     */
    public static function activateBrandingPlugin(): ?string
    {
        $plugin = new Plugin();
        $plugin->checkStates(true); // discover plugins present on disk

        if (!$plugin->getFromDBbyDir('terabras')) {
            return null;
        }

        $id = (int) $plugin->fields['id'];
        $did_something = false;

        if (!$plugin->isInstalled('terabras')) {
            $plugin->install($id);
            $did_something = true;
        }
        if (!$plugin->isActivated('terabras')) {
            $plugin->activate($id);
            $did_something = true;
        }

        return $did_something ? 'Branding plugin installed and activated.' : null;
    }

    /**
     * Install and activate the product's declared plugin set.
     *
     * The set is declared by `TERABRAS_PLUGINS` (comma-separated plugin directories)
     * so the same image can ship different editions, and so activating third-party
     * code — which runs that plugin's own installer — is always an explicit decision
     * rather than something that happens because a directory exists on disk.
     *
     * The pinned versions live in plugins/terabras/PLUGINS.lock.
     */
    public static function activateDeclaredPlugins(): ?string
    {
        $declared = array_filter(array_map('trim', explode(',', (string) getenv('TERABRAS_PLUGINS'))));
        if ($declared === []) {
            return null;
        }

        $plugin = new Plugin();
        $plugin->checkStates(true);

        $activated = [];
        $missing   = [];

        foreach ($declared as $key) {
            if ($key === 'terabras') {
                continue; // always handled by activateBrandingPlugin()
            }

            $p = new Plugin();
            if (!$p->getFromDBbyDir($key)) {
                $missing[] = $key;
                continue;
            }

            $id = (int) $p->fields['id'];
            if (!$p->isInstalled($key)) {
                $p->install($id);
            }
            if (!$p->isActivated($key)) {
                $p->activate($id);
                $activated[] = $key;
            }
        }

        $parts = [];
        if ($activated !== []) {
            $parts[] = 'activated ' . implode(', ', $activated);
        }
        if ($missing !== []) {
            $parts[] = 'WARNING: declared but not present on disk: ' . implode(', ', $missing);
        }

        return $parts === [] ? null : 'Declared plugins: ' . implode('; ', $parts) . '.';
    }

    /**
     * Turn on timezone support.
     *
     * `use_timezones` lives in `config/config_db.php`, written once by the installer
     * from a check that runs before the DB container has necessarily finished loading
     * its timezone tables. Re-evaluating it here (and on every boot) makes the setting
     * converge instead of depending on that race.
     *
     * Mirrors `bin/console database:enable_timezones`, minus the interactive output.
     */
    public static function enableTimezones(): ?string
    {
        global $DB;

        if ($DB === null) {
            return null;
        }

        if ($DB->use_timezones) {
            return null; // already on — nothing to do
        }

        if (!(new DbTimezones($DB))->isValidated()) {
            return 'WARNING: timezone data is not loaded in the database; timezone support stays off.'
                . ' Load it with `mariadb-tzinfo-to-sql /usr/share/zoneinfo | mariadb mysql`.';
        }

        if ($DB->getTzIncompatibleTables()->count() > 0) {
            return 'WARNING: some columns still use the deprecated `datetime` type;'
                . ' run `php bin/console migration:timestamps` before enabling timezones.';
        }

        if (!DBConnection::updateConfigProperty(DBConnection::PROPERTY_USE_TIMEZONES, true)) {
            return 'WARNING: could not write config_db.php to enable timezones (check file permissions).';
        }

        $DB->use_timezones = true;

        return 'Timezone support enabled.';
    }

    /**
     * Product-level configuration defaults that GLPI leaves unset or stale.
     */
    public static function applyProductConfig(): ?string
    {
        $changed = [];
        $updates = [];

        $current = Config::getConfigurationValues('core', [
            'marketplace_replace_plugins',
            'document_max_size',
            'from_email_name',
            'mailing_signature',
            'url_base',
        ]);

        // The installer derives `url_base` from the Referer of the last wizard
        // request (install.php step 8). Behind a reverse proxy, or when the wizard is
        // driven by anything that does not send one, that silently yields the wrong
        // host — and `url_base` is what every generated link, e-mail and the
        // HTTPS/cookie decision are built from. An explicit value wins when given.
        $public_url = rtrim((string) getenv('TERABRAS_PUBLIC_URL'), '/');
        if ($public_url !== '' && ($current['url_base'] ?? '') !== $public_url) {
            $updates['url_base'] = $public_url;
            $changed[] = 'public URL set to ' . $public_url;
        }

        // The plugins page otherwise greets the admin with an unanswered
        // "Do you want to replace the plugins setup page by the new marketplace?".
        // Terabras ships a curated plugin set and does not use the marketplace, so
        // answer it once, explicitly. (The marketplace itself is switched off by
        // `GLPI_MARKETPLACE_ENABLE` in inc/downstream.php.)
        if ((int) ($current['marketplace_replace_plugins'] ?? 0) !== MarketplaceController::MP_REPLACE_NEVER) {
            $updates['marketplace_replace_plugins'] = MarketplaceController::MP_REPLACE_NEVER;
            $changed[] = 'marketplace prompt disabled';
        }

        // GLPI seeds `document_max_size` from `upload_max_filesize` at install time and
        // never revisits it. Keep it aligned with what the stack actually accepts, so the
        // advertised limit is never a promise PHP will refuse to honour.
        $effective_mb = self::getEffectiveUploadLimitMb();
        if ($effective_mb > 0 && (int) ($current['document_max_size'] ?? 0) !== $effective_mb) {
            $updates['document_max_size'] = $effective_mb;
            $changed[] = sprintf('document_max_size aligned to %d MB', $effective_mb);
        }

        // Branding defaults previously carried by install/branding_config.sql, which
        // nothing ever executed.
        if (in_array($current['from_email_name'] ?? '', ['', 'GLPI'], true)) {
            $updates['from_email_name'] = 'Terabras';
            $changed[] = 'mail sender name';
        }
        if (in_array($current['mailing_signature'] ?? '', ['', 'SIGNATURE', 'GLPI'], true)) {
            $updates['mailing_signature'] = 'Terabras';
            $changed[] = 'mail signature';
        }

        if ($updates === []) {
            return null;
        }

        // One call, so the configuration history gets a single entry instead of one per key.
        Config::setConfigurationValues('core', $updates);

        return 'Product configuration applied (' . implode(', ', $changed) . ').';
    }

    /**
     * The real upload ceiling of the stack, in MB.
     *
     * A POST carrying a file must fit in `post_max_size` as well as
     * `upload_max_filesize`, so the smaller of the two is what a user can actually
     * send. `post_max_size` also has to carry the rest of the form, hence the
     * conservative floor() on the smaller value.
     */
    public static function getEffectiveUploadLimitMb(): int
    {
        $upload = Toolbox::return_bytes_from_ini_vars(ini_get('upload_max_filesize'));
        $post   = Toolbox::return_bytes_from_ini_vars(ini_get('post_max_size'));

        // `post_max_size = 0` means "no limit".
        $limits = array_filter([$upload, $post], static fn($v) => $v > 0);
        if ($limits === []) {
            return 0;
        }

        return (int) max(1, floor(min($limits) / 1024 / 1024));
    }

    /**
     * Remove the published default credentials.
     *
     * - `glpi` becomes the product administrator: its password is taken from
     *   `TERABRAS_ADMIN_PASSWORD` when provided (so a pipeline can provision
     *   non-interactively), otherwise a strong one is generated and returned to the
     *   caller for a single display.
     * - `tech`, `normal` and `post-only` are deactivated rather than purged, so
     *   referential integrity and any demo data survive.
     *
     * Runs only while the accounts still carry their factory password, which makes
     * it safe on every boot: once an administrator has set their own password we
     * never touch it again.
     *
     * @param list<string> $messages
     * @return array{name: string, password: ?string}|null
     */
    public static function hardenDefaultAccounts(array &$messages): ?array
    {
        global $DB;

        $deactivated = [];
        foreach (self::DEFAULT_ACCOUNTS as $login) {
            $user = new User();
            if (!$user->getFromDBbyName($login) || (int) $user->fields['is_active'] === 0) {
                continue;
            }
            $DB->update(User::getTable(), ['is_active' => 0], ['id' => (int) $user->fields['id']]);
            $deactivated[] = $login;
        }
        if ($deactivated !== []) {
            $messages[] = 'Default accounts deactivated: ' . implode(', ', $deactivated) . '.';
        }

        $admin = new User();
        if (!$admin->getFromDBbyName(self::FACTORY_ACCOUNT)) {
            return null;
        }

        $admin_name = getenv('TERABRAS_ADMIN_USER') ?: self::DEFAULT_ADMIN_LOGIN;

        // Only act while the factory password is still in place.
        if (!password_verify(self::FACTORY_ACCOUNT, (string) $admin->fields['password'])) {
            return null; // already secured by a previous run or by an administrator
        }

        $password  = getenv('TERABRAS_ADMIN_PASSWORD') ?: null;
        $generated = $password === null;
        if ($generated) {
            $password = self::generatePassword();
        }

        $update = [
            'password'      => Auth::getPasswordHash($password),
            'password_last_update' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ];
        if ($admin_name !== $admin->fields['name']) {
            $update['name'] = $admin_name;
        }
        $DB->update(User::getTable(), $update, ['id' => (int) $admin->fields['id']]);

        $messages[] = sprintf(
            'Administrator account "%s" secured with %s password.',
            $admin_name,
            $generated ? 'a generated' : 'the configured'
        );

        return [
            'name'     => $admin_name,
            // Only hand back a generated password: a configured one is already known
            // to whoever provisioned the instance and must not be echoed back.
            'password' => $generated ? $password : null,
        ];
    }

    /**
     * Make the deployed translation overrides visible.
     *
     * GLPI caches each language catalogue after it has been assembled
     * (Session::loadLanguage() wraps the translator in a persistent cache), so a
     * newly shipped `files/_locales/core/<lang>.php` is ignored on an instance that
     * has already served a page in that language — the white-label wording would
     * only appear after someone happened to clear a cache.
     *
     * Only the TRANSLATIONS cache is cleared, never `resetAllCaches()`: that would
     * also drop the compiled templates and the compiled SCSS, and core `glpi.scss`
     * takes ~29 s to rebuild — long enough to turn a deploy into a visible outage.
     *
     * Keyed on a fingerprint of the deployed files so a restart that changes nothing
     * costs one cheap hash and no cache churn.
     */
    public static function refreshTranslations(): ?string
    {
        $dir = GLPI_LOCAL_I18N_DIR . '/core';
        $source = __DIR__ . '/../locales/core';

        // Deploy the catalogues. The container entrypoint does this too, earlier, so
        // that the very first request already has them; doing it here as well keeps a
        // plain (non-container) checkout self-healing and makes this method the single
        // thing that has to run after the files change.
        if (is_dir($source) && (is_dir($dir) || @mkdir($dir, 0o755, true) || is_dir($dir))) {
            foreach ((array) glob($source . '/*.php') as $file) {
                $target = $dir . '/' . basename($file);
                if (!file_exists($target) || md5_file($target) !== md5_file($file)) {
                    @copy($file, $target);
                }
            }
        }

        $files = is_dir($dir) ? glob($dir . '/*.php') : [];
        if ($files === [] || $files === false) {
            return null;
        }

        sort($files);
        $fingerprint = sha1(implode('', array_map(
            static fn(string $file): string => $file . '|' . (string) @md5_file($file),
            $files
        )));

        if (Config::getConfigurationValue('core', 'terabras_locales_hash') === $fingerprint) {
            return null;
        }

        (new CacheManager())->getTranslationsCacheInstance()->clear();
        Config::setConfigurationValues('core', ['terabras_locales_hash' => $fingerprint]);

        return sprintf('Translation overrides refreshed (%d file(s)); translations cache cleared.', count($files));
    }

    /**
     * Is this instance published over HTTPS?
     *
     * GLPI raises "PHP directive session.cookie_secure should be set to on" only on
     * an HTTPS request (\Glpi\System\Requirement\SessionsSecurityConfiguration), and
     * turning the directive on while the instance is served over plain HTTP breaks
     * every session. The public URL recorded at install time is the only thing that
     * knows which of the two is true, so the decision is read from there rather than
     * left to a hand-set flag that nobody updates when the site moves behind TLS.
     */
    public static function isPubliclyHttps(): bool
    {
        $url_base = (string) Config::getConfigurationValue('core', 'url_base');

        return str_starts_with(strtolower($url_base), 'https://');
    }

    /**
     * Migrate Formcreator data into the core forms tables.
     *
     * Opt-in through `TERABRAS_MIGRATE_FORMCREATOR`, and deliberately NOT on by
     * default: it rewrites customer data, and a container restart is the wrong moment
     * to discover that. When there is data to migrate and the flag is off we say so,
     * so the pending warning on the home page has an obvious, documented resolution
     * instead of just sitting there.
     */
    public static function migrateFormcreator(): ?string
    {
        global $DB;

        if (!class_exists(FormMigration::class) || !class_exists(FormAccessControlManager::class)) {
            return null;
        }

        $migration = new FormMigration($DB, FormAccessControlManager::getInstance());
        if ($migration->hasBeenExecuted() || !$migration->hasPluginData()) {
            return null;
        }

        if (!filter_var(getenv('TERABRAS_MIGRATE_FORMCREATOR'), FILTER_VALIDATE_BOOLEAN)) {
            return 'WARNING: Formcreator data is present and not yet migrated.'
                . ' Run `php bin/console migration:formcreator_plugin_to_core`,'
                . ' or set TERABRAS_MIGRATE_FORMCREATOR=1 to have provisioning do it on the next boot.';
        }

        $result = $migration->execute();

        return $result->isFullyProcessed() && !$result->hasErrors()
            ? 'Formcreator data migrated into the core forms tables.'
            : 'WARNING: Formcreator migration did not complete (it was rolled back); see the application log.';
    }

    /**
     * A password that satisfies GLPI's strongest built-in policy (length, digits,
     * lower/upper case and symbols) without ambiguous glyphs.
     */
    public static function generatePassword(int $length = 20): string
    {
        $alphabets = [
            'abcdefghijkmnopqrstuvwxyz',
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            '23456789',
            '!@#%^&*-_=+?',
        ];

        $chars = [];
        foreach ($alphabets as $alphabet) {
            $chars[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $all = implode('', $alphabets);
        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }
}
