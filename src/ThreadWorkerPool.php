<?php

namespace ProcOpenThreading;

/**
 * @package ProcOpenThreading
 */
class ThreadWorkerPool
{
    /**
     * @var int
     */
    private $numThreads = 1;
    /**
     * @var string
     */
    private $command;
    /**
     * @var Thread[]
     */
    private $threads = array();
    /**
     * @var WorkQueue
     */
    private $workQueue;
    /**
     * @var int
     */
    private $threadTimeout = 120;
    /**
     * @var array
     */
    private $stdoutPipes = array();
    /**
     * @var callable
     */
    private $doneHandler;
    /**
     * @var callable
     */
    private $errorHandler;
    /**
     * @var array
     */
    private $stderrPipes = array();
    /**
     * @var array
     */
    private $collected = array();
    /**
     * @var array
     */
    private $runningJobs = 0;
    /**
     * @var array
     */
    private $errors = array();
    /**
     * @var int[]
     */
    private $free = array();
    /**
     * @var array
     */
    private $shouldClose = array();
    /**
     * @var WorkQueue
     */
    private $retryData;
    /**
     * @var int
     */
    private $numRetries = 1;

    /**
     *
     * @param string $command Path to the StreamEventCommand script
     * @param WorkQueue $workQueue
     * @param array $options
     */
    public function __construct(
        $command,
        WorkQueue $workQueue,
        $options = array()
    ) {
        $this->command = $command;
        $this->retryData = new WorkQueue();
        $this->workQueue = $workQueue;
        if (isset($options["threads"]) && is_int($options["threads"])) {
            $this->numThreads = $options["threads"];
        }
        if (isset($options["thread_timeout"]) && is_int($options["thread_timeout"])) {
            $this->threadTimeout = $options["thread_timeout"];
        }
        if (isset($options["done"]) && is_callable($options["done"])) {
            $this->doneHandler = $options["done"];
        }
        if (isset($options["error"]) && is_callable($options["error"])) {
            $this->errorHandler = $options["error"];
        }
        if (isset($options["retries"]) && is_int($options["retries"])) {
            $this->numRetries = $options["retries"];
        }
    }

    /**
     * Start the work pool
     * @return array
     */
    public function start()
    {
        for ($i = 0; $i < $this->numRetries; $i++) {
            while ($this->retryData->size() > 0) {
                $item = $this->retryData->dequeue();
                $this->workQueue->enqueue($item);
            }
            while ($this->workQueue->size() > 0) {
                $this->createThreads();
                $this->sendJobs();
                $this->process();
            }
            while($this->runningJobs > 0) {
                $this->killFree();
                $this->process();
            }
        }

        for($i = 0; $i < count($this->threads); $i++){
            $this->threads[$i]->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $this->closeThread($i);
        }

        $this->threads = array_values(array_filter($this->threads));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));

        $this->free = array();

        return $this->collected;
    }

    private function killFree()
    {
        $close = array();
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $thread = $this->threads[$id];
            $thread->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $close[] = $id;
        }
        foreach ($close as $id) {
            $this->closeThread($id);
        }
        $this->threads = array_values(array_filter($this->threads));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));
    }

    public final function createThreads()
    {
        if (
        (count($this->threads) < $this->workQueue->size())
        ) {
            $threadsToCreate = min($this->numThreads, $this->workQueue->size()) - count($this->threads);
            for ($x = 0; $x < $threadsToCreate; $x++) {
                $thread = new Thread($this->command, $this->threadTimeout);
                $this->free[] = count($this->threads);
                $this->threads[] = $thread;
                $thread->start();
                $pipes = $thread->getPipes();
                $this->stdoutPipes[] = $pipes[1];
                $this->stderrPipes[] = $pipes[2];
            }
        }
    }

    public final function sendJobs()
    {
        $close = array();
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $thread = $this->threads[$id];
            if (is_null($thread)) {
                continue;
            }
            if ($this->workQueue->size() > 0) {
                $item = $this->workQueue->dequeue();
                $jobData = serialize($item);
                $this->runningJobs++;
                $thread->setJobData($item);
                $thread->tell($jobData);
            } else {
                $thread->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
                $close[] = $id;
            }
        }
        foreach ($close as $id) {
            $this->closeThread($id);
        }
        $this->threads = array_values(array_filter($this->threads));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));
    }

    private function closeThread($id)
    {
        fclose($this->stdoutPipes[$id]);
        fclose($this->stderrPipes[$id]);
        $this->threads[$id]->close();
        $this->threads[$id] = null;
        $this->stdoutPipes[$id] = null;
        $this->stderrPipes[$id] = null;
    }

    public final function stdOut($id)
    {
        $thread = $this->threads[$id];
        if ($thread->isActive()) {
            $data = unserialize($thread->listen());
            $this->collected[] = $data;
            $this->free[] = $id;
            if (is_callable($this->doneHandler)) {
                call_user_func($this->doneHandler, $data);
            }
        }
        $this->runningJobs--;
    }

    public final function stdErr($id)
    {
        $thread = $this->threads[$id];
        $this->retryData->enqueue($thread->getJobData());
        if (!$thread->isActive()) {
            $this->closeThread($id);
            return;
        }
        $errorTxt = $thread->getError();
        $error = unserialize($errorTxt);
        $this->errors[] = $error;
        $this->runningJobs--;
        if (is_callable($this->errorHandler)) {
            call_user_func($this->errorHandler, $error);
        }
        $this->shouldClose[] = $id;
    }

    private function process()
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

    public final function closeShouldClose()
    {
        foreach ($this->shouldClose as $id) {
            $this->closeThread($id);
        }
        $this->shouldClose = array();
    }

    /**
     * @return array
     */
    public final function getStdoutPipes()
    {
        return $this->stdoutPipes;
    }

    /**
     * @return array
     */
    public final function getStderrPipes()
    {
        return $this->stderrPipes;
    }

    /**
     * @return WorkQueue
     */
    public function getWorkQueue()
    {
        return $this->workQueue;
    }

    /**
     * @return array
     */
    public function getRunningJobs()
    {
        return $this->runningJobs;
    }
}