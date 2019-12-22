<?php

use aventri\Multiprocessing\Debug;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    public function testCreateDebugString()
    {
        $string = Debug::cli();
        $this->assertSame($string, "PHP_IDE_CONFIG='serverName=SomeName' php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=host.dokcer.internal");
        $string = Debug::cli([
            "serverName" => "testServerName",
            "xdebug.remote_port" => 9010,
            "auto_prepend_file" => ""
        ]);
        $this->assertSame($string, "PHP_IDE_CONFIG='serverName=testServerName' php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9010 -dxdebug.remote_host=host.dokcer.internal -dauto_prepend_file=");
    }
}