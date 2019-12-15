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
    private $numThreads;
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
    private $threadTimeout;
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
    private $errors;
    /**
     * @var int[]
     */
    private $free = array();
    /**
     * @var array
     */
    private $shouldClose = array();

    /**
     *
     * @param string $command Path to the StreamEventCommand script
     * @param WorkQueue $workQueue
     * @param int $numThreads
     * @param int $threadTimeout
     * @param callable|null $doneHandler
     * @param callable|null $errorHandler
     */
    public function __construct(
        $command,
        WorkQueue $workQueue,
        $numThreads = 1,
        $threadTimeout = 120,
        callable $doneHandler = null,
        callable $errorHandler = null
    ) {
        $this->numThreads = $numThreads;
        $this->command = $command;
        $this->workQueue = $workQueue;
        $this->threadTimeout = $threadTimeout;
        $this->doneHandler = $doneHandler;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Start the work pool
     * @return array
     */
    public function start()
    {
        while ($this->workQueue->size() > 0) {
            $this->createThreads();
            $this->sendJobs();
            $this->process();
        }

        echo "Waiting on running jobs" . PHP_EOL;
        while($this->runningJobs > 0) {
            $this->killFree();
            $this->process();
        }
        echo "Finished running jobs" . PHP_EOL;

        foreach($this->threads as $id => $thread) {
            $thread->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $this->closeThread($id);
        }

        return $this->collected;
    }

    private function killFree()
    {
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $thread = $this->threads[$id];
            $thread->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $this->closeThread($id);
        }
    }

    public final function createThreads()
    {
        if (
        (count($this->threads) < $this->workQueue->size())
        ) {
            $threadsToCreate = min($this->numThreads, $this->workQueue->size()) - count($this->threads);
            if ($threadsToCreate > 0) {
                echo "Creating $threadsToCreate threads" . PHP_EOL;
            }
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
        while(true) {
            $id = array_shift($this->free);
            if (is_null($id)) {
                break;
            }
            $thread = $this->threads[$id];
            if ($this->workQueue->size() > 0) {
                $jobData = serialize($this->workQueue->dequeue());
                $this->runningJobs++;
                $thread->tell($jobData);
            } else {
                $this->shouldClose[] = $id;
            }
        }
    }

    private function closeThread($id)
    {
        fclose($this->stdoutPipes[$id]);
        fclose($this->stderrPipes[$id]);
        $this->threads[$id]->close();
        unset($this->threads[$id]);
        unset($this->stdoutPipes[$id]);
        unset($this->stderrPipes[$id]);
        $this->threads = array_values($this->threads);
        $this->stdoutPipes = array_values($this->stdoutPipes);
        $this->stderrPipes = array_values($this->stderrPipes);
    }

    public final function stdOut($id)
    {
        $thread = $this->threads[$id];
        if (!$thread->isActive()) {
            echo "Stdout Thread not active" . PHP_EOL;
            echo "Buffer " . $thread->listen() . PHP_EOL;
            $this->shouldClose[] = $id;
            return;
        }
        $data = @unserialize($thread->listen());
        $this->collected[] = $data;
        $this->free[] = $id;
        $this->runningJobs--;
        if (is_callable($this->doneHandler)) {
            call_user_func($this->doneHandler, $data);
        }
    }

    public final function stdErr($id)
    {
        $thread = $this->threads[$id];
        if (!$thread->isActive()) {
            echo "Sterr Thread not active" . PHP_EOL;
            $this->shouldClose[] = $id;
            return;
        }
        $errorTxt = $thread->getError();
        $error = @unserialize($errorTxt);
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
        echo "Number of streams: " . count($streams) . PHP_EOL;
        stream_select($read, $write, $except, null);
        foreach ($read as $r) {
            $id = array_search($r, $streams);
            $stdout = true;
            if ($id > count($this->stdoutPipes) - 1) {
                $stdout = false;
                $id = $id - count($this->stdoutPipes);
            }

            if ($stdout) {
//                if (in_array($id, $this->shouldClose)) {
//                    echo "this should be dead";
//                }
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
            if (in_array($id, $this->free)) {
                $index = array_search($id, $this->free);
                unset($this->free[$index]);
            }
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