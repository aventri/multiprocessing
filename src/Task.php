<?php

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\StreamInitializer;
use aventri\Multiprocessing\ProcessPool\SocketPool;
use aventri\Multiprocessing\ProcessPool\StreamPool;
use aventri\Multiprocessing\Tasks\EventTask;
use aventri\Multiprocessing\Tasks\SocketTask;
use aventri\Multiprocessing\Tasks\StreamTask;
use Exception;

abstract class Task extends EventTask
{
    const TYPE_SOCKET = "SOCKET";
    const TYPE_STREAM = "STREAM";
    /**
     * @var EventTask
     */
    private $task;

    public function listen($type = null)
    {
        $stdin = fopen('php://stdin', 'r');
        $initializer = $this->getInitializer($stdin);
        if ($initializer instanceof StreamInitializer) {
            $this->task = new StreamTask($this, $initializer, $stdin);
        } elseif ($initializer instanceof SocketInitializer) {
            $this->task = new SocketTask($this, $initializer);
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