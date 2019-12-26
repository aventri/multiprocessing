<?php

use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Pool\SocketPoolPipeline;
use aventri\Multiprocessing\Pool\StreamPoolPipeline;
use aventri\Multiprocessing\Queues\WorkQueue;
use aventri\Multiprocessing\Task\Task;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $queue1;
    /**
     * @var MockObject
     */
    private $queue2;

    public function setUp()
    {
        $this->queue1 = $this->getMockBuilder(WorkQueue::class)
            ->setMethods([
                "enqueue",
                "dequeue",
                "count"
            ])
            ->getMock();

        $this->queue2 = $this->getMockBuilder(WorkQueue::class)
            ->setMethods([
                "enqueue",
                "dequeue",
                "count"
            ])
            ->getMock();
    }

    public function testStreamPipeline()
    {
        $queue1 = range(1, 30);
        $this->queue1->expects($this->exactly(30))
            ->method("dequeue")
            ->will($this->returnCallback(function() use(&$queue1) {
                return array_shift($queue1);
            }));

        $this->queue1->expects($this->any())
            ->method("count")
            ->will($this->returnCallback(function() use(&$queue1) {
                return count($queue1);
            }));

        $queue2 = [];
        $this->queue2->expects($this->exactly(30))
            ->method("enqueue")
            ->will($this->returnCallback(function($val) use(&$queue2) {
                $queue2[] = $val;
            }));

        $this->queue2->expects($this->exactly(30))
            ->method("dequeue")
            ->will($this->returnCallback(function() use(&$queue2) {
                return array_shift($queue2);
            }));

        $this->queue2->expects($this->any())
            ->method("count")
            ->will($this->returnCallback(function() use(&$queue2) {
                return count($queue2);
            }));

        $done1 = [];
        $done2 = [];
        $error1 = [];
        $error2 = [];
        $task = "php " . realpath(__DIR__ . "/../") . "/proc_scripts/process_test.php";
        $pipeline = new StreamPoolPipeline([
            PoolFactory::create([
                "task" => $task,
                "queue" => $this->queue1,
                "num_processes" => 2,
                "done" => function ($data) use(&$done1) {
                    $done1[] = $data;
                },
                "error" => function (Exception $e) use(&$error1) {
                    $error1[] = $e;
                }
            ]),
            PoolFactory::create([
                "task" => $task,
                "queue" => $this->queue2,
                "num_processes" => 2,
                "done" => function ($data) use(&$done2) {
                    $done2[] = $data;
                },
                "error" => function (Exception $e) use(&$error2) {
                    $error2[] = $e;
                }
            ])
        ]);
        $pipeline->start();
        $this->assertCount(30, $done1);
        $this->assertCount(30, $done2);
        $this->assertCount(0, $error1);
        $this->assertCount(0, $error2);
    }

    public function testSocketPipeline()
    {
        $queue1 = range(1, 30);
        $this->queue1->expects($this->exactly(30))
            ->method("dequeue")
            ->will($this->returnCallback(function() use(&$queue1) {
                return array_shift($queue1);
            }));

        $this->queue1->expects($this->any())
            ->method("count")
            ->will($this->returnCallback(function() use(&$queue1) {
                return count($queue1);
            }));

        $queue2 = [];
        $this->queue2->expects($this->exactly(30))
            ->method("enqueue")
            ->will($this->returnCallback(function($val) use(&$queue2) {
                $queue2[] = $val;
            }));

        $this->queue2->expects($this->exactly(30))
            ->method("dequeue")
            ->will($this->returnCallback(function() use(&$queue2) {
                return array_shift($queue2);
            }));

        $this->queue2->expects($this->any())
            ->method("count")
            ->will($this->returnCallback(function() use(&$queue2) {
                return count($queue2);
            }));

        $done1 = [];
        $done2 = [];
        $error1 = [];
        $error2 = [];
        $task = "php " . realpath(__DIR__ . "/../") . "/proc_scripts/process_test.php";
        $pipeline = new SocketPoolPipeline([
            PoolFactory::create([
                "type" => Task::TYPE_SOCKET,
                "task" => $task,
                "queue" => $this->queue1,
                "num_processes" => 2,
                "done" => function ($data) use(&$done1) {
                    $done1[] = $data;
                },
                "error" => function (Exception $e) use(&$error1) {
                    $error1[] = $e;
                }
            ]),
            PoolFactory::create([
                "type" => Task::TYPE_SOCKET,
                "task" => $task,
                "queue" => $this->queue2,
                "num_processes" => 2,
                "done" => function ($data) use(&$done2) {
                    $done2[] = $data;
                },
                "error" => function (Exception $e) use(&$error2) {
                    $error2[] = $e;
                }
            ])
        ]);
        $pipeline->start();
        $this->assertCount(30, $done1);
        $this->assertCount(30, $done2);
        $this->assertCount(0, $error1);
        $this->assertCount(0, $error2);
    }
}