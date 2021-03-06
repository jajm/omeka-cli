<?php

namespace OmekaCli\Test\Command;

class StatusCommandTest extends TestCase
{
    protected $commandName = 'status';

    public function setUp()
    {
        parent::setUp();

        $this->flushSandboxes();
    }

    public function testIsOutputFormatOk()
    {
        $this->commandTester->execute(array());

        $output = $this->commandTester->getDisplay();
        $this->assertRegExp('|Omeka base directory:\s+' . preg_quote(getenv('OMEKA_PATH')) . '|', $output);
        $this->assertRegExp('/Omeka version:\s+2\.5\.1/', $output);
        $this->assertRegExp('/Database version:\s+2\.5\.1/', $output);
        $this->assertRegExp('/Admin theme:\s+default/', $output);
        $this->assertRegExp('/Public theme:\s+default/', $output);
        $this->assertRegExp('/Installed plugins:\s+0 \(0 active\)/', $output);
        $this->assertRegExp('/Uninstalled plugins:\s+4/', $output);
    }
}
