<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\ProcessPool\Pool;
use aventri\Multiprocessing\ProcessPool\PoolPipeline;
use aventri\Multiprocessing\ProcessPool\SocketPoolPipeline;
use aventri\Multiprocessing\ProcessPool\StreamPool;
use aventri\Multiprocessing\ProcessPool\StreamPoolPipeline;
use InvalidArgumentException;

final class PipelineFactory
{
    /**
     * @param Pool[] $pools
     * @return PoolPipeline
     */
    public static final function create($pools = array())
    {
        $same = true;
        $lastType = null;
        foreach ($pools as $pool) {
            if (is_null($lastType)) {
                $lastType = get_class($pool);
            }
            $same = $same && $lastType === get_class($pool);
        }
        if (!$same) {
            throw new InvalidArgumentException("All pools must be the same type");
        }

        if ($lastType === StreamPool::class) {
            return new StreamPoolPipeline($pools);
        } else {
            return new SocketPoolPipeline($pools);
        }
    }
}