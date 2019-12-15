<?php

include realpath(__DIR__ . "/../../vendor/") . "/autoload.php";

use ProcOpenThreading\StreamEventCommand;

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
        if ($number % 5 == 0) {
//            trigger_error($this->pid);
        }
        $this->write("PID: $this->pid Original Number: $number FIBO: " . $this->fibo($number));
    }
})->listen();


