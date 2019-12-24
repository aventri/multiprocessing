<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Exceptions\SocketException;
use aventri\Multiprocessing\IPC\SocketDataRequest;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\SocketResponse;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Process;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Tasks\EventTask;
use Exception;

class SocketPool extends Pool implements PipelineStepInterface
{
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
                $this->free = array();
                $this->initializeNewProcs($new);
                $this->process();
                $this->sendJobs();
            }
            do {
                $this->killFree();
                $this->process();
            }while($this->runningJobs > 0 or $this->killed < count($this->procs));
        }

        unlink($this->socketFileName);
        return $this->collected;
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
                $this->socketsProcs[(int)$readyToRead] = $this->procs[$header->getProcId()];
                $this->allSockets[] = $readyToRead;
                $this->free[] = $readyToRead;
            } else {
                $input = socket_read($readyToRead, SocketHead::HEADER_LENGTH);
                /** @var SocketHead $header */
                $header = unserialize($input);
                if ($header === false) {
                    $this->subRunningJob();
                    $this->killed++;
                    $this->socketsProcs[(int)$readySocket] = null;
                    $index = array_search($readySocket, $this->allSockets);
                    $this->allSockets[$index] = null;
                    $this->allSockets = array_values(array_filter($this->allSockets));
                } else {
                    $data = socket_read($readyToRead, $header->getBytes());
                    /** @var SocketResponse $data */
                    $data = unserialize($data);
                    $proc = $this->procs[$header->getProcId()];
                    $this->dataReceived($proc, $data->getData());
                    $this->free[] = $readyToRead;
                }
            }
        }
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
                $head = new SocketHead();
                $dataSize = strlen($data);
                $head->setBytes($dataSize);
                $head = SocketHead::pad(serialize($head));
                socket_write($socket, $head.$data, SocketHead::HEADER_LENGTH + $dataSize);
            } else {
                if ($proc->getJobData() === EventTask::DEATH_SIGNAL) continue;
                $proc->setJobData(EventTask::DEATH_SIGNAL);
                $data = serialize(EventTask::DEATH_SIGNAL);
                $head = new SocketHead();
                $dataSize = strlen($data);
                $head->setBytes($dataSize);
                $head = SocketHead::pad(serialize($head));
                socket_write($socket, $head.$data, SocketHead::HEADER_LENGTH + $dataSize);
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
            $head = new SocketHead();
            $dataSize = strlen($data);
            $head->setBytes($dataSize);
            $head = SocketHead::pad(serialize($head));
            socket_write($socket, $head.$data, SocketHead::HEADER_LENGTH + $dataSize);
        }
    }

    public final function initializeNewProcs($new = array())
    {
        if (count($new) === 0) return;
        foreach($new as $id) {
            $initializer = new SocketInitializer();
            $initializer->setUnixSocketFile($this->socketFileName);
            $initializer->setProcId($id);
            $initializer->setPoolId($this->poolId);
            $proc = $this->procs[$id];
            $proc->tell(serialize($initializer) . PHP_EOL);
        }
    }

    public final function dataReceived(Process $proc, $data)
    {
        if ($data instanceof Exception) {
            $this->killed++;
            $this->retryData->enqueue($proc->getJobData());
            $this->runningJobs--;
            if (is_callable($this->errorHandler)) {
                call_user_func($this->errorHandler, $data);
            }
            return null;
        } else if ($data instanceof WakeTime) {
            $this->subRunningJob();
            return null;
        } else {
            $this->runningJobs--;
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

    public final function addKilled()
    {
        $this->killed++;
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
