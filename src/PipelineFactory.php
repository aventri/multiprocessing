<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\ProcessPool\SocketPoolPipeline;
use aventri\Multiprocessing\ProcessPool\StreamPoolPipeline;

final class PipelineFactory
{
    public static final function create($pools = array())
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return new SocketPoolPipeline($pools);
        } else {
            return new StreamPoolPipeline($pools);
        }
    }
}