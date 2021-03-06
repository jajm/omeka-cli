<?php

namespace OmekaCli\Test\Command;

use OmekaCli\Command\UpgradeCommand;
use OmekaCli\Context\Context;
use OmekaCli\Sandbox\OmekaSandbox;
use Symfony\Component\Console\Tester\CommandTester;

class UpgradeCommandTest extends TestCase
{
    protected $commandName = 'upgrade';

    public function testUpgradeNeedsGitRepo()
    {
        $status = $this->commandTester->execute(array());

        $this->assertEquals(1, $status);
        $output = $this->commandTester->getDisplay();
        $this->assertRegExp('/needs a git repo to upgrade/', $output);
    }

    /**
     * @group slow
     */
    public function testUpgrade()
    {
        if (version_compare(PHP_VERSION, '7.1') >= 0) {
            $this->markTestSkipped('Only latest version of Omeka is compatible with PHP 7.1');
        }

        $tempdir = rtrim(`mktemp -d --tmpdir omeka-upgrade-test.XXXXXX`);
        $input = array(
            'omeka-path' => $tempdir,
            '--db-host' => getenv('OMEKA_DB_HOST'),
            '--db-user' => getenv('OMEKA_DB_USER'),
            '--db-pass' => getenv('OMEKA_DB_PASS'),
            '--db-name' => getenv('OMEKA_DB_NAME'),
            '--db-prefix' => 'upgradetest_',
            '--omeka-site-title' => 'UpgradeCommand test',
            '--branch' => 'v2.4',
        );
        $installCommand = $this->getCommand('install');
        $installCommandTester = new CommandTester($installCommand);
        $installCommandTester->execute($input);

        $sandbox = new OmekaSandbox();
        $sandbox->setContext(new Context($tempdir));
        $version = $sandbox->execute(function () {
            return get_option('omeka_version');
        }, OmekaSandbox::ENV_SHORTLIVED);
        $this->assertEquals('2.4', $version);

        $command = $this->getCommand('upgrade');
        $command->getHelper('context')->setContext(new Context($tempdir));
        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(array());

        $this->assertEquals(0, $status);

        $sandbox = new OmekaSandbox();
        $sandbox->setContext(new Context($tempdir));
        $version = $sandbox->execute(function () {
            return get_option('omeka_version');
        }, OmekaSandbox::ENV_SHORTLIVED);
        $this->assertEquals('2.5.1', $version);

        unset($sandbox);
        rrmdir($tempdir);
    }
}
