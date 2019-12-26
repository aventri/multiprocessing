<?php

namespace aventri\Multiprocessing\Pool;

use aventri\Multiprocessing\Mp;
use aventri\Multiprocessing\Process\Process;
use aventri\Multiprocessing\Queues\WorkQueue;
use InvalidArgumentException;

/**
 * Class Pool
 * @package aventri\Multiprocessing\Pool
 * @author Rich Wandell <richwandell@gmail.com>
 */
abstract class Pool extends Mp implements JobStartInterface
{
    /**
     * @var int
     */
    protected static $poolNum = 0;
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
     * @var bool
     */
    protected $verbose = false;
    /**
     * @var int
     */
    protected $poolId;

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
        if (isset($options["verbose"]) && is_bool($options["verbose"])) {
            $this->verbose = $options["verbose"];
        }
        $this->poolId = self::$poolNum;
        self::$poolNum++;
    }

    /**
     * @inheritDoc
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
     * @return int
     */
    public function getPoolId()
    {
        return $this->poolId;
    }

    /**
     * Kills all processes listed in the free process array
     * @return null
     */
    protected abstract function killFree();

    protected abstract function process();

    /**
     * Creates child processes if there is work to do and we have not reached the process number limit.
     * @return Process[] The child process of all created processes
     */
    public final function createProcs()
    {
        $new = array();
        if (count($this->procs) < $this->workQueue->count()) {
            $procsToCreate = min($this->numProcs, $this->workQueue->count()) - count($this->procs);
            if ($this->verbose && $procsToCreate > 0) {
                echo "Creating: $procsToCreate processes" . PHP_EOL;
            }
            for ($x = 0; $x < $procsToCreate; $x++) {
                $proc = new Process($this->command, $this->procTimeout);
                $this->procs[] = $proc;
                $proc->start();
                $new[] = $proc;
            }
        }
        return $new;
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

    public function getProc($id)
    {
        return $this->procs[$id];
    }
}