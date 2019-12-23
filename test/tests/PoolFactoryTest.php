<?php

use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\ProcessPool\SocketPool;
use aventri\Multiprocessing\Queues\WorkQueue;
use PHPUnit\Framework\TestCase;

class PoolFactoryTest extends TestCase
{
    /**
     * @group Windows
     */
    public function testGivesSocketWindows()
    {
        $pool = PoolFactory::create(["task" => "", "queue" => new WorkQueue()]);
        $this->assertInstanceOf(SocketPool::class, $pool);
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