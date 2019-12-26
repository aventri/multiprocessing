<?php

use aventri\Multiprocessing\Example\Steps\Pipeline1\Step2;
use aventri\Multiprocessing\Example\Steps\Pipeline1\StepInterface;
use aventri\Multiprocessing\Task\Task;

include realpath(__DIR__ . "/../../vendor/") . "/autoload.php";

(new class extends Task
{
    /**
     * @var int
     */
    private $pid;

    public function __construct()
    {
        $this->pid = getmypid();
    }

    private function wasteTime($value)
    {
        $result = 1;
        for($i = 1; $i < 10000000; $i++) {
            $result += $i;
        }
        return $value * $value;
    }

    /**
     * @param StepInterface $step
     * @return mixed|void
     */
    public function consume($step)
    {
        $step2 = new Step2($step);
        $step2->pid = $this->pid;
        $startTime = microtime(true);
        $step2->value = $this->wasteTime($step->value);
        $totalTime = microtime(true) - $startTime;
        $step2->totalTime = $totalTime;
        $this->write($step2);
    }
})->listen();



