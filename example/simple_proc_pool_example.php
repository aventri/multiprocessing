<?php

use aventri\ProcOpenMultiprocessing\Queues\WorkQueue;
use aventri\ProcOpenMultiprocessing\WorkerPool;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";
$workScript = "php -dauto_prepend_file= " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";

//create some data to work on, we will calculate fibonacci numbers for these
$work = range(1, 30);
$pool = new WorkerPool(
    $workScript,
    new WorkQueue($work),
    [
        "procs" => 8,
        "done" => function($data) {
            echo "Pool $data" . PHP_EOL;
        },
        "error" => function(Exception $e) {
            echo $e->getTraceAsString() . PHP_EOL;
        }
    ]
);

$collected = $pool->start();
print_r($collected);