<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing\Tasks;

use aventri\Multiprocessing\Exceptions\ChildException;
use aventri\Multiprocessing\IPC\SocketDataRequest;
use aventri\Multiprocessing\IPC\SocketHead;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\SocketResponse;
use aventri\Multiprocessing\IPC\WakeTime;
use aventri\Multiprocessing\Task;
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
     * @var bool
     */
    private $connected;
    /**
     * @var mixed
     */
    private $responseData;
    /**
     * @var int
     */
    private $procId;
    /**
     * @var Task
     */
    private $consumer;

    public function __construct(Task $consumer)
    {
        $this->consumer = $consumer;
    }

    public final function error(Exception $e)
    {
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $connected = socket_connect($this->socket, $this->unixSocketFile);
        if (!$connected) exit(1);
        $this->send($e);
        exit(1);
    }

    /**
     * Writes the data to the output stream.
     * @param mixed $data
     */
    public final function write($data)
    {
        $this->responseData = $data;
    }

    private function send($data)
    {
        $response = new SocketResponse();
        $response->setData($data);
        $response->setProcId($this->procId);
        $message = serialize($response);
        $head = new SocketHead();
        $head->setBytes(strlen($message));
        $head = SocketHead::pad(serialize($head));
        $w1 = socket_write($this->socket, $head, SocketHead::HEADER_LENGTH);
        if ($w1 === false) return false;
        $w2 = socket_write($this->socket, $message, strlen($message));
        if ($w2 === false) return false;
        return true;
    }

    private function read()
    {
        $result = socket_read($this->socket, SocketHead::HEADER_LENGTH);
        /** @var SocketHead $head */
        $head = unserialize($result);
        $data = socket_read($this->socket, $head->getBytes());
        $data = unserialize($data);
        return $data;
    }

    private function getInitializer()
    {
        $stdin = fopen('php://stdin', 'r');
        $buffer = fgets($stdin);
        /** @var SocketInitializer $initializer */
        $initializer = unserialize($buffer);
        $this->unixSocketFile = $initializer->getUnixSocketFile();
        $this->procId = $initializer->getProcId();
        return $initializer;
    }

    /**
     * Start listening for incoming data from STDIN
     */
    public final function listen()
    {
        if (!empty(ob_get_status())) {
            ob_end_clean();
        }
        $this->getInitializer();
        $this->setupErrorHandler();

        while (true) {
            $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            $connected = socket_connect($this->socket, $this->unixSocketFile);
            if (!$connected) exit(1);
            $this->send(new SocketDataRequest());
            $data = $this->read();
            socket_close($this->socket);

            if ($data === self::DEATH_SIGNAL) {
                exit(0);
            }

            if ($data instanceof WakeTime) {
                $this->wakeUpAt($data);
            }

            try {
                $this->consumer->consume($data);
                $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
                $connected = socket_connect($this->socket, $this->unixSocketFile);
                if (!$connected) exit(1);
                $this->send($this->responseData);
            } catch (Exception $e) {
                $ex = new ChildException($e);
                $this->error($ex);
            } finally {
                socket_close($this->socket);
            }
        }
    }
}