<?php

use aventri\ProcOpenMultiprocessing\Example\Steps\Step2;
use aventri\ProcOpenMultiprocessing\Example\Steps\StepInterface;
use aventri\ProcOpenMultiprocessing\StreamEventCommand;

include realpath(__DIR__ . "/../../vendor/") . "/autoload.php";

(new class extends StreamEventCommand
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



