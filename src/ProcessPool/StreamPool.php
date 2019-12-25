<?php

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\StreamInitializer;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Process;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Tasks\EventTask;

/**
 * @package aventri\Multiprocessing;
 */
class StreamPool extends Pool implements PipelineStepInterface
{
    /**
     * @var Process[]
     */
    private $pipesProcs = array();

    /**
     * @var resource[]
     */
    private $readPipes = array();
    /**
     * @var resource[]
     */
    private $stdOutPipes = array();

    /**
     * Start the work pool
     * @return array
     */
    public function start()
    {
        for ($i = 0; $i < $this->numRetries; $i++) {
            while ($this->retryData->count() > 0) {
                $item = $this->retryData->dequeue();
                $this->workQueue->enqueue($item);
            }
            while ($this->workQueue->count() > 0) {
                $new = $this->createProcs();
                $this->initializeNewProcs($new);
                $this->sendJobs();
                $this->process();
            }
            while($this->runningJobs > 0) {
                $this->killFree();
                $this->process();
            }
        }

        while(true) {
            $pipe = array_pop($this->readPipes);
            if (is_null($pipe)) break;

            $proc = $this->pipesProcs[(int)$pipe];
            $proc->tell(serialize(EventTask::DEATH_SIGNAL));
            $proc->close();
            $pipes = $proc->getPipes();
            $index = array_search($proc, $this->procs);
            if ($index !== false) {
                array_splice($this->procs, $index, 1);
            }
            $index = array_search($pipes[1], $this->readPipes);
            if ($index !== false) {
                array_splice($this->readPipes, $index, 1);
            }
            $index = array_search($pipes[2], $this->readPipes);
            if ($index !== false) {
                array_splice($this->readPipes, $index, 1);
            }
        }

        $this->free = array();

        return $this->collected;
    }

    public final function initializeNewProcs($new = array())
    {
        if (count($new) === 0) return;
        foreach($new as $id => $proc) {
            $pipes = $proc->getPipes();
            foreach ($pipes as $pipe) {
                $this->pipesProcs[(int)$pipe] = $proc;
            }
            $this->readPipes[] = $pipes[1];
            $this->stdOutPipes[] = $pipes[1];
            $this->readPipes[] = $pipes[2];
            $this->free[] = $pipes[0];
            $initializer = new StreamInitializer();
            $initializer->setProcId($id);
            $initializer->setPoolId($this->poolId);
            $proc->tell(serialize($initializer). PHP_EOL);
        }
    }

    protected function killFree()
    {
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $proc = $this->pipesProcs[(int)$id];
            $proc->tell(serialize(EventTask::DEATH_SIGNAL));
        }
    }

    public function sendJobs()
    {
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $proc = $this->pipesProcs[(int)$id];
            if (is_null($proc)) {
                continue;
            }
            if ($this->workQueue->count() > 0) {
                $item = $this->workQueue->dequeue();
                if (is_null($item) and $this->workQueue instanceof RateLimitedQueue) {
                    $jobData = serialize($this->workQueue->getWakeTime());
                    $this->runningJobs++;
                    $proc->setJobData($item);
                    $proc->tell($jobData);
                } else {
                    $jobData = serialize($item);
                    $this->runningJobs++;
                    $proc->setJobData($item);
                    $proc->tell($jobData . PHP_EOL);
                }
            } else {
                $proc->tell(serialize(EventTask::DEATH_SIGNAL));
            }
        }
    }

    public final function stdOut($r)
    {
        $proc = $this->pipesProcs[(int)$r];
        if ($proc->isActive()) {
            $this->runningJobs--;
            $this->free[] = $r;
            $data = unserialize($proc->listen());
            if ($data instanceof WakeTime) {
                return null;
            }
            $this->collected[] = $data;
            if (is_callable($this->doneHandler)) {
                call_user_func($this->doneHandler, $data);
            }
            return $data;
        }
        return null;
    }

    public final function stdErr($r)
    {
        $proc = $this->pipesProcs[(int)$r];
        if (!$proc->isActive()) {
            $pipes = $proc->getPipes();
            $this->retryData->enqueue($proc->getJobData());
            $stdOutIndex = array_search($pipes[1], $this->readPipes);
            array_splice($this->readPipes, $stdOutIndex, 1);
            $stdErrIndex = array_search($pipes[2], $this->readPipes);
            array_splice($this->readPipes, $stdErrIndex, 1);
            $proc->close();
            $index = array_search($proc, $this->procs);
            if ($index !== false) {
                array_splice($this->procs, $index, 1);
            }
            return;
        }
        $errorTxt = $proc->getError();
        $error = unserialize($errorTxt);
        if ($error === false) {
            return;
        }
        $this->retryData->enqueue($proc->getJobData());
        $this->errors[] = $error;
        $this->runningJobs--;
        if (is_callable($this->errorHandler)) {
            call_user_func($this->errorHandler, $error);
        }
    }

    protected function process()
    {
        $streams = $this->readPipes;
        $read = $streams;
        $write = null;
        $except = null;
        stream_select($read, $write, $except, null);
        foreach ($read as $r) {
            $this->dataReceived($r);
        }
    }

    public function getReadPipes()
    {
        return $this->readPipes;
    }

    public final function dataReceived($pipe)
    {
        $stdout = in_array($pipe, $this->stdOutPipes);
        if ($stdout) {
            return $this->stdOut($pipe);
        } else {
            $this->stdErr($pipe);
        }
    }
}