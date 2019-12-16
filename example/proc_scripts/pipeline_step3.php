<?php

use aventri\ProcOpenMultiprocessing\Example\Steps\Step3;
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

    private function wasteMoreTime($value)
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
        $step3 = new Step3($step);
        $step3->pid = $this->pid;
        $startTime = microtime(true);
        $step3->value = $this->wasteMoreTime($step3->value);
        $totalTime = microtime(true) - $startTime;
        $step3->totalTime = $totalTime;
        $this->write($step3);
    }
})->listen();



