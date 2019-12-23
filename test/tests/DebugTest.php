<?php

use aventri\Multiprocessing\Debug;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    public function testCreateDebugString()
    {
        $windowsServerName1 = 'set PHP_IDE_CONFIG="serverName=SomeName" &&';
        $windowsServerName2 = 'set PHP_IDE_CONFIG="serverName=testServerName" &&';
        $macLinuxServerName1 = "PHP_IDE_CONFIG='serverName=SomeName'";
        $macLinuxServerName2 = "PHP_IDE_CONFIG='serverName=testServerName'";
        $string1 = " php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=host.docker.internal";
        $string2 = " php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9010 -dxdebug.remote_host=host.docker.internal -dauto_prepend_file=";

        $debugString = Debug::cli();
        $this->assertSame((IS_WINDOWS ? $windowsServerName1 : $macLinuxServerName1) . $string1, $debugString);
        $debugString = Debug::cli([
            "serverName" => "testServerName",
            "xdebug.remote_port" => 9010,
            "auto_prepend_file" => ""
        ]);
        $this->assertSame((IS_WINDOWS ? $windowsServerName2 : $macLinuxServerName2) . $string2, $debugString);
    }
}