<?php

namespace aventri\Multiprocessing;

use InvalidArgumentException;

final class Debug
{
    const DEFAULT_SERVER_NAME = "SomeName";

    private static $defaultParameters = array(
        "xdebug.remote_enable" => 1,
        "xdebug.remote_mode" => "req",
        "xdebug.remote_port" => 9000,
        "xdebug.remote_host" => "host.docker.internal"
    );

    public static function cli($options = array())
    {
        if (!is_array($options)) {
            throw new InvalidArgumentException("Options must be an array");
        }

        $serverName = self::DEFAULT_SERVER_NAME;
        if (isset($options["serverName"])) {
            $serverName = $options["serverName"];
            unset($options["serverName"]);
        }
        $string = "PHP_IDE_CONFIG='serverName=$serverName' php";

        $params = array_merge(self::$defaultParameters, $options);
        foreach ($params as $key => $val) {
            $string .= " -d$key=$val";
        }

        return $string;
    }
}