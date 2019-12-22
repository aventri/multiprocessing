<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Exceptions\SocketException;
use aventri\Multiprocessing\IPC\SocketDataRequest;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\SocketResponse;
use aventri\Multiprocessing\Queues\RateLimitedQueue;
use aventri\Multiprocessing\Queues\WorkQueue;
use aventri\Multiprocessing\Tasks\EventTask;
use Exception;

class SocketPool extends Pool
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
     * @param $lineNumber
     * @throws SocketException
     */
    private function throwSocketException($lineNumber)
    {
        $code = socket_last_error();
        $message = socket_strerror($code);
        throw new SocketException($message, $code, $severity = 1, $filename = __FILE__, $lineNumber);
    }

    /**
     * @inheritDoc
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
            $this->throwSocketException(__LINE__);
        }
        $bound = socket_bind($this->socket, $this->socketFileName);
        if (!$bound) {
            $this->throwSocketException(__LINE__);
        }
        $listening = socket_listen($this->socket, self::BACKLOG);
        if (!$listening) {
            $this->throwSocketException(__LINE__);
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
            }
            do {
                $this->process();
            }while($this->runningJobs > 0 or $this->killed < count($this->procs));
        }

        return $this->collected;
    }

    private function initializeNewProcs($new = array())
    {
        if (count($new) === 0) return;
        foreach($new as $id) {
            $initializer = new SocketInitializer();
            $initializer->setUnixSocketFile($this->socketFileName);
            $initializer->setProcId($id);
            $proc = $this->procs[$id];
            $proc->tell(serialize($initializer). PHP_EOL);
        }
    }

    /**
     * @throws SocketException
     */
    protected function process()
    {
        $spawn = socket_accept($this->socket);
        if ($spawn === false) {
            $this->throwSocketException(__LINE__);
        }
        $input = socket_read($spawn, SocketHead::HEADER_LENGTH);
        if ($input === false) {
            $this->throwSocketException(__LINE__);
        }
        /** @var SocketHead $head */
        $head = unserialize($input);
        $response = socket_read($spawn, $head->getBytes());
        if ($response === false) {
            $this->throwSocketException(__LINE__);
        }
        /** @var SocketResponse $response */
        $response = unserialize($response);
        $data = $response->getData();
        $proc = $this->procs[$response->getProcId()];
        if ($data instanceof SocketDataRequest) {
            if ($this->workQueue->count() > 0) {
                $item = $this->workQueue->dequeue();
                $proc->setJobData($item);
                $data = serialize($item);
                $this->runningJobs++;
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
                $this->killed++;
            }
        } else if ($data instanceof Exception) {
            $this->killed++;
            $this->retryData->enqueue($proc->getJobData());
            $this->runningJobs--;
            if (is_callable($this->errorHandler)) {
                call_user_func($this->errorHandler, $data);
            }
        } else {
            $this->runningJobs--;
            $this->collected[] = $data;
            if (is_callable($this->doneHandler)) {
                call_user_func($this->doneHandler, $data);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function sendJobs()
    {
        // TODO: Implement sendJobs() method.
    }

    /**
     * @inheritDoc
     */
    protected function killFree()
    {
        // TODO: Implement killFree() method.
    }
}
