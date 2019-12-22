<?php

use aventri\Multiprocessing\Example\Steps\Pipeline1\Step1;
use aventri\Multiprocessing\StreamEventCommand;

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

    private function fibo($number)
    {
        if ($number == 0) {
            return 0;
        } else if ($number == 1) {
            return 1;
        }

        return ($this->fibo($number - 1) + $this->fibo($number - 2));
    }

    public function consume($data)
    {
        $number = (int)$data;
        $step1 = new Step1();
        $step1->pid = $this->pid;
        $step1->originalNumber = $number;
        $startTime = microtime(true);
        $step1->value = $this->fibo($number);
        $totalTime = microtime(true) - $startTime;
        $step1->totalTime = $totalTime;
        $this->write($step1);
    }
})->listen();


