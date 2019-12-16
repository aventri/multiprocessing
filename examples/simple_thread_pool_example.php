<?php

use ProcOpenThreading\StreamEventCommand;
use ProcOpenThreading\Thread;
use ProcOpenThreading\ThreadWorkerPool;
use ProcOpenThreading\WorkQueue;


include realpath(__DIR__ . "/../vendor/") . "/autoload.php";
$threadScript = "php " . realpath(__DIR__) . "/thread_scripts/fibo_thread.php";

//create some data to work on, we will calculate fibonacci numbers for these
$work = range(1, 30);
$pool = new ThreadWorkerPool(
    $threadScript,
    new WorkQueue($work),
    [
        "threads" => 2,
        "done" => function($data) {
            echo "Pool $data" . PHP_EOL;
        },
        "error" => function(ErrorException $e) {
            echo $e->getTraceAsString() . PHP_EOL;
        }
    ]
);

$start = microtime(true);
$collected = $pool->start();
$time = microtime(true) - $start;
print_r($collected);
//50.007667064667
echo $time . PHP_EOL;