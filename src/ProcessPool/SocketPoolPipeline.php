<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Example\Steps\Pipeline1\Step2;
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
     * @var array
     */
    private $allSockets = array();
    /**
     * @var array
     */
    private $poolIdPool = array();
    /**
     * @var SocketPool[]
     */
    private $socketsPools = array();

    /**
     * @return array
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
        $this->allSockets[] = [$this->socket];
        $bound = socket_bind($this->socket, $this->socketFileName);
        if (!$bound) {
            $this->throwSocketException(__FILE__, __LINE__);
        }
        $listening = socket_listen($this->socket, SocketPool::BACKLOG);
        if (!$listening) {
            $this->throwSocketException(__FILE__, __LINE__);
        }

        $allSize = 0;
        /** @var SocketPool $pool */
        foreach ($this->procWorkerPools as $pool) {
            $allSize += $pool->getWorkQueue()->count();
            $allSize += $pool->getRunningJobs();
            $this->poolIdPool[$pool->getPoolId()] = $pool;
            $this->allSockets[] = array();
            $pool->setUnixSocketFileName($this->socketFileName);
        }

        while ($allSize > 0) {
            $allSize = 0;
            /** @var SocketPool $pool */
            foreach ($this->procWorkerPools as $pool) {
                $allSize += $pool->getWorkQueue()->count();
                $allSize += $pool->getRunningJobs();
                $new = $pool->createProcs();
                $pool->initializeNewProcs($new);
                $pool->sendJobs();
            }
            if ($allSize > 0) {
                $this->process();
            }
        }

        return $this->procWorkerPools[count($this->procWorkerPools) - 1]->getCollected();
    }

    /**
     * @throws SocketException
     */
    protected function process()
    {
        $read = array();
        foreach ($this->allSockets as $allSocket) {
            $read = array_merge($read, $allSocket);
        }
        $write  = null;
        $except = NULL;
        //wait for some socket activity
        socket_select($read, $write, $except, null);
        foreach($read as $readySocket) {
            $readyToRead = $readySocket;
            if ($readySocket === $this->allSockets[0][0]) {
                //we have a new socket connection, save it
                $readyToRead = socket_accept($this->socket);
                $input = socket_read($readyToRead, SocketHead::HEADER_LENGTH);
                /** @var SocketHead $header */
                $header = unserialize($input);
                $poolId = $header->getPoolId();
                $this->allSockets[$poolId + 1][] = $readyToRead;
                /** @var SocketPool $pool */
                $pool = $this->poolIdPool[$poolId];
                $this->socketsPools[(int)$readyToRead] = $pool;
                $pool->newSocketConnected($header, $readyToRead);
            } else {
                $pool = $this->socketsPools[(int)$readyToRead];
                $dataTmp = socket_read($readyToRead, SocketPool::MAX_READ);
                $data = unserialize($dataTmp);
                if ($data === false) {
                    for($i = 1; $i < count($this->allSockets); $i++) {
                        $index = array_search($readySocket, $this->allSockets[$i]);
                        if ($index !== false) {
                            array_splice($this->allSockets[$i], $index, 1);
                            break;
                        }
                    }
                    $pool->noDataReceived($readyToRead);
                } else {
                    $data = $pool->dataReceived($data, $readyToRead);
                    $index = array_search($pool, $this->procWorkerPools);
                    if ($index === 0 and $data->originalNumber == 30) {
                        echo "hi";
                    }
                    if (!is_null($data)) {
                        if (isset($this->procWorkerPools[$index + 1])) {
                            $this->procWorkerPools[$index + 1]->getWorkQueue()->enqueue($data);
                        }
                    }
                }
            }
        }
    }
}