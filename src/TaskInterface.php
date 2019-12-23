<?php

namespace aventri\Multiprocessing;

interface TaskInterface
{
    /**
     * Start listening for communication with the parent process
     */
    public function listen();

    /**
     * Write data back to the parent process.
     * @param $data
     * @return mixed
     */
    public function write($data);

    /**
     * Receives data from the parent process.
     * @param mixed $data
     * @return mixed
     */
    public function consume($data);
}