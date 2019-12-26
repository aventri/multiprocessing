<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\Pool\Pool;
use aventri\Multiprocessing\Pool\PoolPipeline;
use aventri\Multiprocessing\Pool\SocketPoolPipeline;
use aventri\Multiprocessing\Pool\StreamPool;
use aventri\Multiprocessing\Pool\StreamPoolPipeline;
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