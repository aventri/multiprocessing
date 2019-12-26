<?php

namespace aventri\Multiprocessing\Task;

use aventri\Multiprocessing\IPC\Initializer;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\StreamInitializer;

abstract class Task implements TaskInterface
{
    const TYPE_SOCKET = "SOCKET";
    const TYPE_STREAM = "STREAM";
    /**
     * @var EventTask
     */
    private $task;

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function write($data)
    {
        $this->task->write($data);
    }

    /**
     * @inheritDoc
     */
    abstract function consume($data);

    /**
     * @return Initializer
     */
    protected function getInitializer($stdin)
    {
        $buffer = fgets($stdin);
        /** @var Initializer $initializer */
        $initializer = unserialize($buffer);
        return $initializer;
    }
}