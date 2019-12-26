<?php

use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Pool\SocketPool;
use aventri\Multiprocessing\Pool\StreamPool;
use aventri\Multiprocessing\Queues\WorkQueue;
use PHPUnit\Framework\TestCase;

class PoolFactoryTest extends TestCase
{
    public function testGivesCorrectSocket()
    {
        $pool = PoolFactory::create(["task" => "", "queue" => new WorkQueue()]);
        if (IS_WINDOWS) {
            $this->assertInstanceOf(SocketPool::class, $pool);
        } else {
            $this->assertInstanceOf(StreamPool::class, $pool);
        }
    }

    public function testCreateArguments()
    {
        $exception = null;
        try {
            //no task specified
            PoolFactory::create();
        } catch (Exception $e) {
            $exception = $e;
        }
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);

        $exception = null;
        try {
            //no queue specified
            PoolFactory::create(["task" => ""]);
        } catch (Exception $e) {
            $exception = $e;
        }
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);

        $exception = null;
        try {
            //queue is not a WorkQueue instance
            PoolFactory::create(["task" => "", "queue" => ""]);
        } catch (Exception $e) {
            $exception = $e;
        }
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);

        $exception = null;
        try {
            //queue is not a WorkQueue instance
            PoolFactory::create(["task" => "", "queue" => new WorkQueue()]);
        } catch (Exception $e) {
            $exception = $e;
        }
        $this->assertNull($exception);
    }
}