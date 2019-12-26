<?php

use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Queues\WorkQueue;
use aventri\Multiprocessing\Task\Task;
use aventri\Multiprocessing\Pool\Pool;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";

$workScript =  "php  " . realpath(__DIR__) . "/proc_scripts/echo_proc.php";
$cpuCount = ezcSystemInfo::getInstance()->cpuCount;

$streamPool = PoolFactory::create([
    "type" => Task::TYPE_STREAM,
    "task" => $workScript,
    "queue" => new WorkQueue(array_fill(0, 100000, 1)),
    "num_processes" => $cpuCount
]);

$socketPool = PoolFactory::create([
    "type" => Task::TYPE_SOCKET,
    "task" => $workScript,
    "queue" => new WorkQueue(array_fill(0, 100000, 1)),
    "num_processes" => $cpuCount
]);

function timePool(Pool $pool)
{
    $start = microtime(true);
    $out = $pool->start();
    $totalTime = microtime(true) - $start;
    return [$out, $totalTime];
}

list($streamOut, $streamTime) = timePool($streamPool);
list($socketOut, $socketTime) = timePool($socketPool);

$streamSocketDiff = count(array_diff($streamOut, $socketOut));
$socketStreamDiff = count(array_diff($socketOut, $streamOut));

echo "Diff: $streamSocketDiff $socketStreamDiff" . PHP_EOL;
echo "Stream: $streamTime Socket: $socketTime" . PHP_EOL;
