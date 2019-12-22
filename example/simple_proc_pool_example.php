<?php

use aventri\ProcOpenMultiprocessing\Debug;
use aventri\ProcOpenMultiprocessing\Queues\RateLimitedQueue;
use aventri\ProcOpenMultiprocessing\Queues\WorkQueue;
use aventri\ProcOpenMultiprocessing\WorkerPool;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";

$debug = Debug::cli(["xdebug.remote_port" => 9010]);
$workScript =  "$debug  " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";
$collected = (new WorkerPool(
    $workScript,
    new WorkQueue(range(1, 30)),
    [
        "procs" => 8,
        "done" => function($data) {
            echo "Pool $data" . PHP_EOL;
        },
        "error" => function(Exception $e) {
            echo $e->getTraceAsString() . PHP_EOL;
        }
    ]
))->start();

print_r($collected);