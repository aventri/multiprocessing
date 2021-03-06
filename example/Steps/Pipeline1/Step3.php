<?php

namespace aventri\Multiprocessing\Example\Steps\Pipeline1;

class Step3 implements StepInterface
{
    public $value;
    public $pid;
    public $totalTime;
    /**
     * @var StepInterface
     */
    private $step;

    public function __construct(StepInterface $step)
    {
        $this->step = $step;
    }

    public function getTime()
    {
        return $this->totalTime + $this->step->getTime();
    }

    public function getResult()
    {
        return $this->step->getResult() . " PID: $this->pid Value: $this->value";
    }

    public function getValue()
    {
        return $this->value;
    }
}