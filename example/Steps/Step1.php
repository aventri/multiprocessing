<?php

namespace aventri\ProcOpenMultiprocessing\Example\Steps;

class Step1 implements StepInterface
{
    public $pid;
    public $value;
    public $originalNumber;
    public $totalTime;

    public function getResult()
    {
        return "Original Number: $this->originalNumber PID: $this->pid Value: $this->value";
    }

    public function getTime()
    {
        return $this->totalTime;
    }

    public function getValue()
    {
        return $this->value;
    }
}