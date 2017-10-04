<?php

namespace OmekaCli\Command;

use Omeka_Db_Migration_Manager;
use OmekaCli\Omeka;

class UpgradeCommand extends AbstractCommand
{
    public function getDescription()
    {
        return 'upgrade Omeka';
    }

    public function getUsage()
    {
        $usage = 'Usage:' . PHP_EOL
               . '    upgrade' . PHP_EOL
               . PHP_EOL
               . 'All saves are put in the ~/.omeka-cli/backups directory.'
               . PHP_EOL;

        return $usage;
    }

    public function run($options, $args)
    {
        $omekaPath = $this->getContext()->getOmekaPath();

        if (!$omekaPath) {
            $this->logger->error('not in an Omeka directory');

            return 1;
        }

        $omeka = $this->getOmeka();
        if (!file_exists($omeka->BASE_DIR . '/.git')) {
            $this->logger->error('omeka-cli needs a git repo to upgrade Omeka');

            return 1;
        }

        $this->logger->info('checking for updates');
        $latestVersion = $this->getLatestVersion();
        if (version_compare($omeka->OMEKA_VERSION, $latestVersion) >= 0) {
            $this->logger->notice('Omeka is already up-to-date');

            return 0;
        }

        $this->logger->info('saving database');
        if (false === $this->saveDb()) {
            $this->logger->error('database dumping failed');

            return 1;
        }

        $this->logger->info('upgrading Omeka');
        if (false === $this->upgradeOmeka($latestVersion)) {
            $this->logger->error('cannot upgrade Omeka');

            return 1;
        }

        $this->logger->info('upgrading database');
        if (false === $this->upgradeDb($latestVersion)) {
            $this->logger->error('Failed to upgrade database');

            return 1;
        }

        $this->logger->notice('Upgrade successful');

        return 0;
    }

    protected function getLatestVersion()
    {
        $latestTag = rtrim(`git ls-remote -q --tags --refs https://github.com/omeka/Omeka 'v*' | cut -f 2 | sed 's|refs/tags/||' | sort -rV | head -n1`);
        $latestVersion = ltrim($latestTag, 'v');

        return $latestVersion;
    }

    protected function saveDb()
    {
        $backupsDir = getenv('HOME') . '/.omeka-cli/backups';
        if (!is_dir($backupsDir)) {
            mkdir($backupsDir, 0777, true);
        }

        $db = parse_ini_file($this->getOmeka()->BASE_DIR . '/db.ini');
        $dest = sprintf('%s/sql/%s-%s.sql.gz', $backupsDir, $db['dbname'], date('YmdHis'));
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }

        $passwordFile = tempnam(sys_get_temp_dir(), 'omeka.cnf.');
        file_put_contents($passwordFile, '[client]' . PHP_EOL . "password = {$db['password']}");
        $mysqldump_cmd = 'mysqldump'
            . ' --defaults-file=' . escapeshellarg($passwordFile)
            . ' --host=' . escapeshellarg($db['host'])
            . ' --user=' . escapeshellarg($db['username'])
            . ' ' . escapeshellarg($db['dbname'])
            . ' | gzip -c > ' . escapeshellarg($dest);

        exec($mysqldump_cmd, $out, $exitCode);

        unlink($passwordFile);

        if ($exitCode !== 0) {
            $this->logger->error('mysqldump failed');

            return false;
        }

        $this->logger->notice('database saved in {dest}', array('dest' => $dest));

        return true;
    }

    protected function upgradeOmeka($version)
    {
        $baseDir = escapeshellarg($this->getOmeka()->BASE_DIR);
        exec("git -C $baseDir stash save -q 'Stash made by omeka-cli upgrade'");
        exec("git -C $baseDir fetch -q origin");
        exec("git -C $baseDir reset --hard -q v$version", $out, $ans);

        return $ans === 0;
    }

    protected function upgradeDb($version)
    {
        try {
            $this->getSandbox()->execute(function () use ($version) {
                $migrationMgr = Omeka_Db_Migration_Manager::getDefault();
                if ($migrationMgr->canUpgrade()) {
                    $migrationMgr->migrate();
                    set_option(Omeka_Db_Migration_Manager::VERSION_OPTION_NAME, $version);
                }
            });
        } catch (\Exception $e) {
            $this->logger->error('Database migration failed: {message}', array('message' => $e->getMessage()));

            return false;
        }

        return true;
    }
}
