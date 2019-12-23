<?php

use aventri\Multiprocessing\Example\Steps\Pipeline2\AlphaVantage;
use aventri\Multiprocessing\PipelineFactory;
use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\WorkerPool;
use aventri\Multiprocessing\WorkerPoolPipeline;
use aventri\Multiprocessing\Queues\WorkQueue;

include realpath(__DIR__."/../vendor/")."/autoload.php";

$step1 = "php ".realpath(__DIR__)."/proc_scripts/pipeline_2_step1.php";
$step2 = "php ".realpath(__DIR__)."/proc_scripts/pipeline_2_step2.php";

$result = PipelineFactory::create([
    PoolFactory::create([
        "task" => $step1,
        "queue" => new WorkQueue(["AAPL", "GOOG", "MSFT"]),
        "num_processes" => 3,
        "done" => function (AlphaVantage $data) {
            echo "Pool 1: ". count($data->timeSeries) . " data points " . PHP_EOL;
        },
        "error" => function (Exception $e) {
            echo $e->getTraceAsString().PHP_EOL;
        }
    ]),
    PoolFactory::create([
        "task" => $step2,
        "queue" => new WorkQueue(),
        "num_processes" => 3,
        "done" => function ($data) {
            echo "Pool 2: " . $data . PHP_EOL;
        },
        "error" => function (Exception $e) {
            echo $e->getTraceAsString().PHP_EOL;
        }
    ])
])->start();

print_r($result);