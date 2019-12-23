<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Exceptions\SocketException;
use aventri\Multiprocessing\IPC\SocketDataRequest;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketResponse;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Tasks\EventTask;
use Exception;
use InvalidArgumentException;

class SocketPoolPipeline extends PoolPipeline
{
    /**
     * @var string
     */
    private $socketFileName;
    /**
     * @var resource
     */
    private $socket;

    /**
     * @return array
     * @throws SocketException
     */
    public function start()
    {
        $tmpDir = sys_get_temp_dir();
        $this->socketFileName = "$tmpDir/ampipc-" . getmypid() . ".sock";
        if (file_exists($this->socketFileName)) {
            unlink($this->socketFileName);
        }
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $bound = socket_bind($this->socket, $this->socketFileName);
        if (!$bound) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $listening = socket_listen($this->socket, SocketPool::BACKLOG);
        if (!$listening) {
            $this->throwSocketException(__FILE__, __LINE__);
        }

        $allSize = 0;
        foreach ($this->procWorkerPools as $pool) {
            $allSize += $pool->getWorkQueue()->count();
            $allSize += $pool->getRunningJobs();
            $pool->setUnixSocketFileName($this->socketFileName);
        }

        while ($allSize > 0) {
            $allSize = 0;
            foreach ($this->procWorkerPools as $pool) {
                $allSize += $pool->getWorkQueue()->count();
                $allSize += $pool->getRunningJobs();
                $new = $pool->createProcs();
                $pool->initializeNewProcs($new);
            }
            if ($allSize > 0) {
                $this->process();
            }
        }

        do {
            $this->process();
            list($numKilled, $numProcs) = $this->getKilledAndProcsCount();
        }while($numKilled < $numProcs);

        return $this->procWorkerPools[count($this->procWorkerPools) - 1]->getCollected();
    }

    private function getKilledAndProcsCount()
    {
        $killed = 0;
        $procs = 0;
        foreach($this->procWorkerPools as $pool) {
            $killed += $pool->getNumKilled();
            $procs += $pool->getNumProcs();
        }
        return array($killed, $procs);
    }

    private function getAllQueueSize()
    {
        $allSize = 0;
        foreach ($this->procWorkerPools as $pool) {
            $allSize += $pool->getWorkQueue()->count();
        }
        return $allSize;
    }

    /**
     * @throws SocketException
     */
    protected function process()
    {
        $spawn = socket_accept($this->socket);
        if ($spawn === false) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $input = socket_read($spawn, SocketHead::HEADER_LENGTH);
        if ($input === false) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        /** @var SocketHead $head */
        $head = unserialize($input);
        $response = socket_read($spawn, $head->getBytes());
        if ($response === false) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        /** @var SocketResponse $response */
        $response = unserialize($response);
        $data = $response->getData();
        $poolId = $response->getPoolId();
        $pool = $this->procWorkerPools[$poolId];
        $proc = $pool->getProc($response->getProcId());
        if ($data instanceof SocketDataRequest) {
            $workQueue = $pool->getWorkQueue();
            if ($workQueue->count() > 0) {
                $item = $workQueue->dequeue();
                if (is_null($item) and $workQueue instanceof RateLimitedQueue) {
                    $item = $workQueue->getWakeTime();
                }
                $proc->setJobData($item);
                $data = serialize($item);
                $pool->addRunningJob();
                $head = new SocketHead();
                $head->setBytes(strlen($data));
                $head = SocketHead::pad(serialize($head));
                socket_write($spawn, $head, SocketHead::HEADER_LENGTH);
                socket_write($spawn, $data, strlen($data));
            } else {
                $data = serialize(EventTask::DEATH_SIGNAL);
                $head = new SocketHead();
                $head->setBytes(strlen($data));
                $head = SocketHead::pad(serialize($head));
                socket_write($spawn, $head, SocketHead::HEADER_LENGTH);
                socket_write($spawn, $data, strlen($data));
                $pool->addKilled();
            }
        } else {
            $data = $pool->dataRecieved($proc, $data);
            if (!is_null($data)) {
                if (isset($this->procWorkerPools[$poolId + 1])) {
                    $this->procWorkerPools[$poolId + 1]->getWorkQueue()->enqueue($data);
                }
            }
        }
    }
}