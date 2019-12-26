<?php

use aventri\Multiprocessing\Pool\SocketPool;
use aventri\Multiprocessing\Queues\WorkQueue;
use aventri\Multiprocessing\Task\Task;
use PHPUnit\Framework\TestCase;

class SocketPoolTest extends TestCase
{
    public function testEcho()
    {
        $done = array();
        $error = array();
        $task = "php " . realpath(__DIR__ . "/../") . "/proc_scripts/process_test.php";
        $queue = new WorkQueue(range(1, 20));
        $pool = new SocketPool([
            "type" => Task::TYPE_SOCKET,
            "task" => $task,
            "queue" => $queue,
            "num_processes" => 2,
            "done" => function ($data) use(&$done) {
                $done[] = $data;
            },
            "error" => function (Exception $e) use(&$error) {
                $error[] = $e;
            }
        ]);
        $out = $pool->start();
        foreach(range(1, 20) as $num) {
            $this->assertTrue(in_array($num, $out));
        }
        $this->assertSame($done, $out);
        $this->assertEmpty($error);
    }
}