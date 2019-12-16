<?php

namespace aventri\ProcOpenMultiprocessing\Example\Steps;

interface StepInterface
{
    public function getResult();
    public function getTime();
    public function getValue();
}