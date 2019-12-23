<?php

namespace aventri\Multiprocessing\ProcessPool;

interface JobStartInterface
{
    /**
     * Start the work pool loop.
     *
     * Implementing classes should use a loop that
     * 1. monitors the work queue
     * 2. creates processes as needed
     * 3. sends jobs to the free processes
     * 4. awaits responses from finished jobs
     * 5. kills all processes after all work is finished
     * @return array Returns the unordered finished work
     */
    public function start();
}