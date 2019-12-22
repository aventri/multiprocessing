<?php

use aventri\Multiprocessing\Debug;
use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Queues\WorkQueue;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";

$debug = Debug::cli(["xdebug.remote_port" => 9010]);
$workScript =  "$debug  " . realpath(__DIR__) . "/proc_scripts/fibo_proc.php";
$collected = (PoolFactory::create([
    "task" => $workScript,
    "queue" => new WorkQueue(range(1, 30)),
    "num_processes" => 8,
    "done" => function($data) {
        echo "Pool $data" . PHP_EOL;
    },
    "error" => function(Exception $e) {
        echo $e->getTraceAsString() . PHP_EOL;
    }
]))->start();

print_r($collected);