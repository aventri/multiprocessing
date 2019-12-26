<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\Task;

use aventri\Multiprocessing\Exceptions\ChildException;
use aventri\Multiprocessing\Exceptions\SocketException;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Pool\SocketPool;
use \Exception;

/**
 * @package aventri\Multiprocessing;
 */
class SocketTask extends EventTask
{
    /**
     * @var string
     */
    private $unixSocketFile;
    /**
     * @var resource
     */
    private $socket;
    /**
     * @var mixed
     */
    private $responseData;
    /**
     * @var Task
     */
    private $consumer;


    public function __construct(Task $consumer, SocketInitializer $initializer)
    {
        $this->consumer = $consumer;
        $this->unixSocketFile = $initializer->getUnixSocketFile();
        $this->procId = $initializer->getProcId();
        $this->poolId = $initializer->getPoolId();
    }

    /**
     * @inheritDoc
     */
    public final function error(Exception $e)
    {
        $this->send($e);
        exit(1);
    }

    /**
     * @inheritDoc
     */
    public final function write($data)
    {
        $this->responseData = $data;
    }

    private function send($data)
    {
        $message = serialize($data);
        $dataLength = strlen($message);
        socket_write($this->socket, $message, $dataLength);
    }

    private function read()
    {
        $data = socket_read($this->socket, SocketPool::MAX_READ);
        $data = unserialize($data);
        return $data;
    }

    /**
     * @inheritDoc
     */
    public final function listen()
    {
        if (!empty(ob_get_status())) {
            ob_end_clean();
        }
        $this->setupErrorHandler();
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $connected = socket_connect($this->socket, $this->unixSocketFile);
        if (!$connected) {
            throw new SocketException("socket not connected");
        }
        $header = new SocketHead();
        $header->setBytes(0);
        $header->setPoolId($this->poolId);
        $header->setProcId($this->procId);
        $message = SocketHead::pad(serialize($header));
        socket_write($this->socket, $message, SocketHead::HEADER_LENGTH);

        while (true) {
            $data = $this->read();

            if ($data === self::DEATH_SIGNAL) {
                exit(0);
            }

            if ($data instanceof WakeTime) {
                $this->wakeUpAt($data);
                $this->send($this->responseData);
                continue;
            }

            try {
                $this->consumer->consume($data);
                $this->send($this->responseData);
            } catch (Exception $e) {
                $ex = new ChildException($e);
                $this->error($ex);
            }
        }
    }
}