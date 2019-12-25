<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Exceptions\SocketException;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Tasks\EventTask;
use Exception;

class SocketPool extends Pool implements PipelineStepInterface
{
    const MAX_READ = 1000000;

    const BACKLOG = 30;
    /**
     * @var string
     */
    private $socketFileName;
    /**
     * @var resource
     */
    private $socket;
    /**
     * @var int
     */
    private $killed = 0;
    /**
     * @var resource[]
     */
    private $allSockets = array();

    /**
     * @var array
     */
    private $socketsProcs = array();
    /**
     * @var array
     */
    private $nonWriteableSockets = array();

    /**
     * @inheritDoc
     * @throws SocketException
     */
    public function start()
    {
        $tmpDir = sys_get_temp_dir();
        $this->socketFileName = "$tmpDir/ampipc-" . getmypid() . ".sock";
        @unlink($this->socketFileName);
        if (file_exists($this->socketFileName)) {
            unlink($this->socketFileName);
        }
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $this->allSockets[] = $this->socket;
        $bound = socket_bind($this->socket, $this->socketFileName);
        if (!$bound) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $listening = socket_listen($this->socket, self::BACKLOG);
        if (!$listening) {
            $this->throwSocketException(__FILE__, __LINE__);
        }

        for ($i = 0; $i < $this->numRetries; $i++) {
            while ($this->retryData->count() > 0) {
                $item = $this->retryData->dequeue();
                $this->workQueue->enqueue($item);
            }
            while ($this->workQueue->count() > 0) {
                $new = $this->createProcs();
                $this->initializeNewProcs($new);
                $this->process();
                $this->sendJobs();
            }
            while($this->runningJobs > 0) {
                $this->killFree();
                $this->process();
            }
        }

        unlink($this->socketFileName);
        return $this->collected;
    }

    public final function newSocketConnected(SocketHead $header, $socket)
    {
        $this->socketsProcs[(int)$socket] = $this->procs[$header->getProcId()];
        $this->allSockets[] = $socket;
        $this->free[] = $socket;
    }

    public function process()
    {
        $read   = $this->allSockets;
        $write  = null;
        $except = NULL;
        //wait for some socket activity
        socket_select($read, $write, $except, null);
        foreach($read as $readySocket) {
            $readyToRead = $readySocket;
            if ($readySocket === $this->allSockets[0]) {
                //we have a new socket connection, save it
                $readyToRead = socket_accept($this->socket);
                $input = socket_read($readyToRead, SocketHead::HEADER_LENGTH);
                /** @var SocketHead $header */
                $header = unserialize($input);
                $this->newSocketConnected($header, $readyToRead);
            } else {
                $data = socket_read($readyToRead, self::MAX_READ);
                $data = unserialize($data);
                if ($data === false) {
                    $this->noDataReceived($readyToRead);
                } else {
                    $this->dataReceived($data, $readyToRead);
                }
            }
        }
    }

    public function noDataReceived($socket)
    {
        $this->killed++;
        $index = array_search($socket, $this->allSockets);
        array_splice($this->allSockets, $index, 1);
    }

    public function sendJobs()
    {
        while(true) {
            $socket = array_shift($this->free);
            if (is_null($socket)) {
                break;
            }
            $proc = $this->socketsProcs[(int)$socket];
            if (is_null($proc)) {
                continue;
            }
            if ($this->workQueue->count() > 0) {
                $item = $this->workQueue->dequeue();
                if (is_null($item) and $this->workQueue instanceof RateLimitedQueue) {
                    $item = $this->workQueue->getWakeTime();
                }
                $proc->setJobData($item);
                $data = serialize($item);
                $this->addRunningJob();
                $dataSize = strlen($data);
                socket_write($socket, $data, $dataSize);
            } else {
                $proc->setJobData(EventTask::DEATH_SIGNAL);
                $data = serialize(EventTask::DEATH_SIGNAL);
                $dataSize = strlen($data);
                socket_write($socket, $data, $dataSize);
                $index = array_search($proc, $this->procs);
                array_splice($this->procs, $index, 1);
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function killFree()
    {
        while(true) {
            $socket = array_shift($this->free);
            if (is_null($socket)) {
                break;
            }
            $proc = $this->socketsProcs[(int)$socket];
            if ($proc->getJobData() === EventTask::DEATH_SIGNAL) continue;
            $proc->setJobData(EventTask::DEATH_SIGNAL);
            $data = serialize(EventTask::DEATH_SIGNAL);
            $dataSize = strlen($data);
            socket_write($socket, $data, $dataSize);
        }
    }

    public final function initializeNewProcs($new = array())
    {
        if (count($new) === 0) return;
        foreach($new as $id => $proc) {
            $initializer = new SocketInitializer();
            $initializer->setUnixSocketFile($this->socketFileName);
            $initializer->setProcId($id);
            $initializer->setPoolId($this->poolId);
            $proc->tell(serialize($initializer) . PHP_EOL);
        }
    }

    public final function dataReceived($data, $socket)
    {
        $proc = $this->socketsProcs[(int)$socket];
        if ($data instanceof Exception) {
            $this->killed++;
            $this->retryData->enqueue($proc->getJobData());
            $this->subRunningJob();
            if (is_callable($this->errorHandler)) {
                call_user_func($this->errorHandler, $data);
            }
            return null;
        } else if ($data instanceof WakeTime) {
            $this->subRunningJob();
            return null;
        } else {
            $this->free[] = $socket;
            $this->subRunningJob();
            $this->collected[] = $data;
            if (is_callable($this->doneHandler)) {
                call_user_func($this->doneHandler, $data);
            }
            return $data;
        }
    }

    public final function addRunningJob()
    {
        $this->runningJobs++;
    }

    public final function subRunningJob()
    {
        $this->runningJobs--;
    }

    public final function getNumKilled()
    {
        return $this->killed;
    }

    public final function getNumProcs()
    {
        return count($this->procs);
    }

    public final function retry($data)
    {
        $this->retryData->enqueue($data);
    }

    public final function setUnixSocketFileName($fileName)
    {
        $this->socketFileName = $fileName;
    }
}
