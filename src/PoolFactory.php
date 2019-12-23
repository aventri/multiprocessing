<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\ProcessPool\SocketPool;
use aventri\Multiprocessing\ProcessPool\StreamPool;
use aventri\Multiprocessing\Tasks\SocketTask;
use aventri\Multiprocessing\Tasks\StreamTask;
use InvalidArgumentException;

final class PoolFactory
{
    public final static function create($options = array())
    {
        if (isset($options["type"])) {
            switch ($options["type"]) {
                case Task::TYPE_STREAM:
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        throw new InvalidArgumentException("Windows stream support disabled, please use socket type");
                    }
                    return new StreamPool($options);
                    break;

                case Task::TYPE_SOCKET:
                    return new SocketPool($options);
                    break;
            }
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return new SocketPool($options);
        } else {
            return new StreamPool($options);
        }
    }
}