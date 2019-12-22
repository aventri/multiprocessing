<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\ProcessPool\SocketPool;
use aventri\Multiprocessing\ProcessPool\StreamPool;
use aventri\Multiprocessing\Tasks\EventTask;
use aventri\Multiprocessing\Tasks\SocketTask;
use aventri\Multiprocessing\Tasks\StreamTask;
use Exception;

abstract class Task extends EventTask
{
    /**
     * @var EventTask
     */
    private $task;

    public function listen()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->task = new SocketTask($this);
        } else {
            $this->task = new StreamTask($this);
        }
        $this->task->listen();
    }

    public function error(Exception $e)
    {
        $this->task->error($e);
    }

    public function write($data)
    {
        $this->task->write($data);
    }

    /**
     * When a stream event is received, the consume method is called.
     * @param mixed $data
     * @return mixed
     */
    abstract function consume($data);
}