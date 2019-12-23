<?php

use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\WorkerPool;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";


$workScript = "php  " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";

$collected = PoolFactory::create([
    "task" => $workScript,
    "queue" => new RateLimitedQueue(
        4,
        new DateInterval("PT5S"),
        range(1, 30)
    ),
    "num_processes" => 8,
    "done" => function($data) {
        echo "$data" . PHP_EOL;
    }
])->start();

print_r($collected);