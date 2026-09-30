<?php

/**
 * Terabras — `plugins:terabras:postinstall`.
 *
 * CLI counterpart of the wizard's final step. Provisioning is idempotent, so the
 * container entrypoint runs this on every boot: a fresh install gets configured,
 * an existing one is left alone.
 *
 * @see \GlpiPlugin\Terabras\Provisioning
 */

namespace GlpiPlugin\Terabras\Console;

use Glpi\Console\AbstractCommand;
use Glpi\Console\Command\ConfigurationCommandInterface;
use GlpiPlugin\Terabras\Provisioning;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostinstallCommand extends AbstractCommand implements ConfigurationCommandInterface
{
    protected function configure()
    {
        parent::configure();

        $this->setName('plugins:terabras:postinstall');
        $this->setDescription('Apply the Terabras product provisioning (timezones, product config, account hardening).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = Provisioning::run();

        foreach ($result['messages'] as $message) {
            $style = str_starts_with($message, 'WARNING') ? 'comment' : 'info';
            $output->writeln(sprintf('<%1$s>%2$s</%1$s>', $style, $message));
        }

        if (($result['admin']['password'] ?? null) !== null) {
            $output->writeln('');
            $output->writeln('<comment>Generated administrator credentials (shown only once):</comment>');
            $output->writeln(sprintf('  login:    <info>%s</info>', $result['admin']['name']));
            $output->writeln(sprintf('  password: <info>%s</info>', $result['admin']['password']));
            $output->writeln('');
            $output->writeln('<comment>Set TERABRAS_ADMIN_PASSWORD before the first run to provision it yourself.</comment>');
        }

        if ($result['messages'] === []) {
            $output->writeln('<info>Nothing to do: instance is already provisioned.</info>');
        }

        // Machine-readable marker for the container entrypoint. GLPI only complains
        // about `session.cookie_secure` on an HTTPS request, and only the database
        // knows the public URL, so the entrypoint asks us and turns the directive on
        // before Apache starts. See .docker/terabras/entrypoint.sh.
        $output->writeln(sprintf('TERABRAS_PUBLIC_HTTPS=%d', Provisioning::isPubliclyHttps() ? 1 : 0));

        return 0;
    }

    /**
     * `enableTimezones()` rewrites `use_timezones` in the DB config file.
     */
    public function getConfigurationFilesToUpdate(InputInterface $input): array
    {
        return ['config_db.php'];
    }
}
