<?php

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Process;
use aventri\Multiprocessing\Queues\WorkQueue;
use InvalidArgumentException;

/**
 * Class Pool
 * @package aventri\Multiprocessing\ProcessPool
 * @author Rich Wandell <richwandell@gmail.com>
 */
abstract class Pool
{
    /**
     * @var int
     */
    protected $numProcs = 1;
    /**
     * @var string
     */
    protected $command;
    /**
     * @var Process[]
     */
    protected $procs = array();
    /**
     * @var WorkQueue
     */
    protected $workQueue;
    /**
     * @var int
     */
    protected $procTimeout = 120;
    /**
     * @var array
     */
    protected $stdoutPipes = array();
    /**
     * @var callable
     */
    protected $doneHandler;
    /**
     * @var callable
     */
    protected $errorHandler;
    /**
     * @var array
     */
    protected $stderrPipes = array();
    /**
     * @var array
     */
    protected $collected = array();
    /**
     * @var int
     */
    protected $runningJobs = 0;
    /**
     * @var array
     */
    protected $errors = array();
    /**
     * @var int[]
     */
    protected $free = array();
    /**
     * @var array
     */
    protected $shouldClose = array();
    /**
     * @var WorkQueue
     */
    protected $retryData;
    /**
     * @var int
     */
    protected $numRetries = 1;

    /**
     * Options are
     * "task" => (required) The full command path to use for the child process including php interpreter command.
     * "queue" => (required) Must be an instance of WorkQueue
     * "num_processes" => The number of processes to use for the pool
     * "proc_timeout" => Timeout for the child processes
     * "done" => A callback function to use for receiving messages in real time from child processes
     * "error" => A callback function to use for receiving errors from child processes
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (!isset($options["task"])) {
            throw new InvalidArgumentException('Missing "task"');
        }
        if (!isset($options["queue"])) {
            throw new InvalidArgumentException('Missing "queue"');
        }
        if (!($options["queue"] instanceof WorkQueue)) {
            throw new InvalidArgumentException('"queue" must be instance of WorkQueue');
        }
        $this->command = $options["task"];
        $this->retryData = new WorkQueue();
        $this->workQueue = $options["queue"];
        if (isset($options["num_processes"]) && is_int($options["num_processes"])) {
            $this->numProcs = $options["num_processes"];
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
     * Start the work pool loop.
     *
     * Implementing classes should use a loop that
     * 1. moniitors the work queue
     * 2. creates processes as needed
     * 3. sends jobs to the free processes
     * 4. awaits responses from finished jobs
     * 5. kills all processes after all work is finished
     * @return array Returns the unordered finished work
     */
    public abstract function start();

    /**
     * Sends jobs to the free processes.
     *
     * 1. FIFO pull a free processes from the free process array.
     * 2. Dequeue a job from the work queue and sends the job to the child process.
     * @return null
     */
    public abstract function sendJobs();

    /**
     * Kills all processes listed in the free process array
     * @return null
     */
    protected abstract function killFree();

    protected abstract function process();

    public final function createProcs()
    {
        if (count($this->procs) < $this->workQueue->count()) {
            $procsToCreate = min($this->numProcs, $this->workQueue->count()) - count($this->procs);
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

    protected function closeProc($id)
    {
        fclose($this->stdoutPipes[$id]);
        fclose($this->stderrPipes[$id]);
        $this->procs[$id]->close();
        $this->procs[$id] = null;
        $this->stdoutPipes[$id] = null;
        $this->stderrPipes[$id] = null;
    }

    protected function resetProcs()
    {
        $this->procs = array_values(array_filter($this->procs));
        $this->stdoutPipes = array_values(array_filter($this->stdoutPipes));
        $this->stderrPipes = array_values(array_filter($this->stderrPipes));
    }

    public final function closeShouldClose()
    {
        foreach ($this->shouldClose as $id) {
            $this->closeProc($id);
        }
        $this->shouldClose = array();
    }

    /**
     * @return resource[]
     */
    public final function getStdoutPipes()
    {
        return $this->stdoutPipes;
    }

    /**
     * @return resource[]
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
     * @return int
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