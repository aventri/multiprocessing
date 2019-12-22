<?php

use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\WorkerPool;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";


$workScript = "php  " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";
$data = range(1, 30);
$queue = new RateLimitedQueue(
    new DateInterval("PT5S"),
    2,
    $data
);

$collected = (new WorkerPool(
    $workScript,
    $queue,
    [
        "procs" => 8,
        "done" => function($data) {
            echo "$data" . PHP_EOL;
        }
    ]
))->start();

print_r($collected);