<?php

namespace aventri\Multiprocessing\Pool;

interface PipelineStepInterface
{
    public function getWorkQueue();
    public function getRunningJobs();
    public function createProcs();
    public function getProc($id);
    public function initializeNewProcs($new = array());
}