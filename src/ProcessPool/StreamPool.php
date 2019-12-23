<?php

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\StreamInitializer;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Tasks\EventTask;

/**
 * @package aventri\Multiprocessing;
 */
class StreamPool extends Pool implements PipelineStepInterface
{
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

        for($i = 0; $i < count($this->procs); $i++){
            $this->procs[$i]->tell(serialize(EventTask::DEATH_SIGNAL));
            $this->closeProc($i);
        }

        $this->resetProcs();

        $this->free = array();

        return $this->collected;
    }

    public final function initializeNewProcs($new = array())
    {
        if (count($new) === 0) return;
        foreach($new as $id) {
            $initializer = new StreamInitializer();
            $initializer->setProcId($id);
            $initializer->setPoolId($this->poolId);
            $proc = $this->procs[$id];
            $proc->tell(serialize($initializer). PHP_EOL);
        }
    }

    protected function killFree()
    {
        $close = array();
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $proc = $this->procs[$id];
            $proc->tell(serialize(EventTask::DEATH_SIGNAL));
            $close[] = $id;
        }
        foreach ($close as $id) {
            $this->closeProc($id);
        }
        $this->resetProcs();
    }

    public function sendJobs()
    {
        $close = array();
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $proc = $this->procs[$id];
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
                $close[] = $id;
            }
        }
        foreach ($close as $id) {
            $this->closeProc($id);
        }
        $this->resetProcs();
    }

    public final function stdOut($id)
    {
        $proc = $this->procs[$id];
        $this->runningJobs--;
        if ($proc->isActive()) {
            $this->free[] = $id;
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

    public final function stdErr($id)
    {
        $proc = $this->procs[$id];
        if (!$proc->isActive()) {
            $this->retryData->enqueue($proc->getJobData());
            $this->closeProc($id);
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
        $this->shouldClose[] = $id;
    }

    protected function process()
    {
        $streams = array_merge($this->stdoutPipes, $this->stderrPipes);
        $read = $streams;
        $write = null;
        $except = null;
        stream_select($read, $write, $except, null);
        foreach ($read as $r) {
            $id = array_search($r, $streams);
            $stdout = true;
            if ($id > count($this->stdoutPipes) - 1) {
                $stdout = false;
                $id = $id - count($this->stdoutPipes);
            }

            if ($stdout) {
                $this->stdOut($id);
            } else {
                $this->stdErr($id);
            }
        }

        $this->closeShouldClose();
    }
}