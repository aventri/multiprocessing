<?php

namespace aventri\ProcOpenMultiprocessing;

/**
 * @package aventri\Multiprocessing;
 */
class WorkerPool
{
    /**
     * @var int
     */
    private $numProcs = 1;
    /**
     * @var string
     */
    private $command;
    /**
     * @var Process[]
     */
    private $procs = array();
    /**
     * @var WorkQueue
     */
    private $workQueue;
    /**
     * @var int
     */
    private $procTimeout = 120;
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
        if (isset($options["procs"]) && is_int($options["procs"])) {
            $this->numProcs = $options["procs"];
        }
        if (isset($options["proc_timeout"]) && is_int($options["proc_timeout"])) {
            $this->procTimeout = $options["proc_timeout"];
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
                $this->createProcs();
                $this->sendJobs();
                $this->process();
            }
            while($this->runningJobs > 0) {
                $this->killFree();
                $this->process();
            }
        }

        for($i = 0; $i < count($this->procs); $i++){
            $this->procs[$i]->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $this->closeProc($i);
        }

        $this->procs = array_values(array_filter($this->procs));
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
            $proc = $this->procs[$id];
            $proc->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
            $close[] = $id;
        }
        foreach ($close as $id) {
            $this->closeProc($id);
        }
        $this->procs = array_values(array_filter($this->procs));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));
    }

    public final function createProcs()
    {
        if (
        (count($this->procs) < $this->workQueue->size())
        ) {
            $procsToCreate = min($this->numProcs, $this->workQueue->size()) - count($this->procs);
            for ($x = 0; $x < $procsToCreate; $x++) {
                $proc = new Process($this->command, $this->procTimeout);
                $this->free[] = count($this->procs);
                $this->procs[] = $proc;
                $proc->start();
                $pipes = $proc->getPipes();
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
            $proc = $this->procs[$id];
            if (is_null($proc)) {
                continue;
            }
            if ($this->workQueue->size() > 0) {
                $item = $this->workQueue->dequeue();
                $jobData = serialize($item);
                $this->runningJobs++;
                $proc->setJobData($item);
                $proc->tell($jobData);
            } else {
                $proc->tell(serialize(StreamEventCommand::DEATH_SIGNAL));
                $close[] = $id;
            }
        }
        foreach ($close as $id) {
            $this->closeProc($id);
        }
        $this->procs = array_values(array_filter($this->procs));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));
    }

    private function closeProc($id)
    {
        fclose($this->stdoutPipes[$id]);
        fclose($this->stderrPipes[$id]);
        $this->procs[$id]->close();
        $this->procs[$id] = null;
        $this->stdoutPipes[$id] = null;
        $this->stderrPipes[$id] = null;
    }

    public final function stdOut($id)
    {
        $proc = $this->procs[$id];
        $this->runningJobs--;
        if ($proc->isActive()) {
            $data = unserialize($proc->listen());
            $this->collected[] = $data;
            $this->free[] = $id;
            if (is_callable($this->doneHandler)) {
                call_user_func($this->doneHandler, $data);
            }
            return $data;
        }
    }

    public final function stdErr($id)
    {
        $proc = $this->procs[$id];
        $this->retryData->enqueue($proc->getJobData());
        if (!$proc->isActive()) {
            $this->closeProc($id);
            return;
        }
        $errorTxt = $proc->getError();
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
            $this->closeProc($id);
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

    /**
     * @return array
     */
    public function getCollected()
    {
        return $this->collected;
    }
}