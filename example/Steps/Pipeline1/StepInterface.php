<?php

namespace aventri\Multiprocessing\Example\Steps\Pipeline1;

interface StepInterface
{
    public function getResult();
    public function getTime();
    public function getValue();
}