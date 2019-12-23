<?php

use aventri\Multiprocessing\Debug;
use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Queues\WorkQueue;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";

$workScript =  "php  " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";
$workQueue = new WorkQueue(range(1, 30));
$collected = PoolFactory::create([
    "task" => $workScript,
    "queue" => $workQueue,
    "num_processes" => 16,
    "retries" => 1,
    "done" => function($data) use($workQueue) {
        echo "Pool $data" . PHP_EOL;
    },
    "error" => function(Exception $e) {
        echo $e->getTraceAsString() . PHP_EOL;
    }
])->start();

print_r($collected);