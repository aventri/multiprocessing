<?php

namespace aventri\ProcOpenMultiprocessing\Example\Steps\Pipeline1;

interface StepInterface
{
    public function getResult();
    public function getTime();
    public function getValue();
}