<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\ProcessPool\SocketPool;
use aventri\Multiprocessing\ProcessPool\StreamPool;

final class PoolFactory
{
    public static function create($options = array())
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return new SocketPool($options);
        } else {
            return new StreamPool($options);
        }
    }
}