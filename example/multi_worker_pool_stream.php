<?php

use aventri\ProcOpenMultiprocessing\Example\Steps\StepInterface;
use aventri\ProcOpenMultiprocessing\WorkerPool;
use aventri\ProcOpenMultiprocessing\WorkerPoolPipeline;
use aventri\ProcOpenMultiprocessing\WorkQueue;

include realpath(__DIR__ . "/../vendor/") . "/autoload.php";


$step1 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_step1.php";
$step2 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_step2.php";
$step3 = "php " . realpath(__DIR__) . "/proc_scripts/pipeline_step3.php";

$pipeline = new WorkerPoolPipeline([
    new WorkerPool(
        $step1,
        new WorkQueue(range(1, 30)),
        [
            "procs" => 2,
            "done" => function (StepInterface $step) {
                echo "Pool 1: " . $step->getResult() . PHP_EOL;
            },
            "error" => function (ErrorException $e) {
                echo $e->getTraceAsString().PHP_EOL;
            }
        ]
    ),
    new WorkerPool(
        $step2,
        new WorkQueue(),
        [
            "procs" => 4,
            "done" => function (StepInterface $step) {
                echo "Pool 2: " . $step->getResult() . PHP_EOL;
            },
            "error" => function (ErrorException $e) {
                echo $e->getTraceAsString().PHP_EOL;
            }
        ]
    ),
    new WorkerPool(
        $step3,
        new WorkQueue(),
        [
            "procs" => 4,
            "done" => function (StepInterface $step) {
                echo "Pool 3: " . $step->getResult() . PHP_EOL;
                echo "Whole Process took: " . $step->getTime() . PHP_EOL;
            },
            "error" => function (ErrorException $e) {
                echo $e->getTraceAsString().PHP_EOL;
            }
        ]
    )
]);

$start = microtime(true);
$collected = $pipeline->start();
$time = microtime(true) - $start;
print_r($collected);