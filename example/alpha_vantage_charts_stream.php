<?php

use aventri\Multiprocessing\Example\Steps\Pipeline2\AlphaVantage;
use aventri\Multiprocessing\WorkerPool;
use aventri\Multiprocessing\WorkerPoolPipeline;
use aventri\Multiprocessing\Queues\WorkQueue;

include realpath(__DIR__."/../vendor/")."/autoload.php";

$step1 = "php ".realpath(__DIR__)."/proc_scripts/pipeline_2_step1.php";
$step2 = "php ".realpath(__DIR__)."/proc_scripts/pipeline_2_step2.php";

$result = (new WorkerPoolPipeline([
    new WorkerPool(
        $step1,
        new WorkQueue(["AAPL", "GOOG", "MSFT"]),
        [
            "procs" => 3,
            "done" => function (AlphaVantage $data) {
                echo "Pool 1: ". count($data->timeSeries) . " data points " . PHP_EOL;
            },
            "error" => function (Exception $e) {
                echo $e->getTraceAsString().PHP_EOL;
            }
        ]
    ),
    new WorkerPool(
        $step2,
        new WorkQueue(),
        [
            "procs" => 3,
            "done" => function ($data) {
                echo "Pool 2: " . $data . PHP_EOL;
            },
            "error" => function (Exception $e) {
                echo $e->getTraceAsString().PHP_EOL;
            }
        ]
    )
]))->start();

print_r($result);