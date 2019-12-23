<?php

use aventri\Multiprocessing\Example\Steps\Pipeline1\StepInterface;
use aventri\Multiprocessing\PipelineFactory;
use aventri\Multiprocessing\PoolFactory;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\WorkerPool;
use aventri\Multiprocessing\WorkerPoolPipeline;
use aventri\Multiprocessing\Queues\WorkQueue;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";


$step1 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_1_step1.php";
$step2 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_1_step2.php";
$step3 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_1_step3.php";

$pipeline = PipelineFactory::create([
    PoolFactory::create([
        "task" => $step1,
        "queue" => new WorkQueue(range(1, 30)),
        "num_processes" => 8,
        "done" => function (StepInterface $step) {
            echo "Pool 1: " . $step->getResult() . PHP_EOL;
        },
        "error" => function (ErrorException $e) {
            echo $e->getTraceAsString().PHP_EOL;
        }
    ]),
    PoolFactory::create([
        "task" => $step2,
        "queue" => new RateLimitedQueue(5, new DateInterval("PT5S")),
        "num_processes" => 8,
        "done" => function (StepInterface $step) {
            echo "Pool 2: " . $step->getResult() . PHP_EOL;
        },
        "error" => function (Exception $e) {
            echo $e->getTraceAsString().PHP_EOL;
        }
    ]),
    PoolFactory::create([
        "task" => $step3,
        "queue" => new WorkQueue(),
        "num_processes" => 8,
        "done" => function (StepInterface $step) {
            echo "Pool 3: " . $step->getResult() . PHP_EOL;
            echo "Whole Process took: " . $step->getTime() . PHP_EOL;
        },
        "error" => function (Exception $e) {
            echo $e->getTraceAsString().PHP_EOL;
        }
    ])
]);

$collected = $pipeline->start();
print_r($collected);