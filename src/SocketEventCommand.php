<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\ProcOpenMultiprocessing;

use aventri\ProcOpenMultiprocessing\Exceptions\ChildErrorException;
use aventri\ProcOpenMultiprocessing\Exceptions\ChildException;
use DateTime;
use \Exception;

/**
 * @package aventri\ProcOpenMultiprocessing;
 */
abstract class SocketEventCommand extends EventCommand
{
    public function error(Exception $e)
    {
        fwrite(STDERR, serialize($e));
        exit(1);
    }

    /**
     * Writes the data to the output stream.
     * @param mixed $data
     */
    public function write($data)
    {
        echo serialize($data);
    }

    /**
     * Start listening for incoming data from STDIN
     */
    public function listen()
    {
        ob_end_clean();
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $result = socket_connect($socket, "/tmp/" . __FILE__ . ".sock");



        $this->setupErrorHandler();

        while (true) {
            $result = socket_read($socket, 1024);

            //don't try to unserialize if we have nothing ready from STDIN, this will save cpu cycles
            if ($buffer === "") {
                continue;
            }
            $data = unserialize($buffer);
            if ($data === self::DEATH_SIGNAL) {
                exit(0);
            }
            if ($data instanceof WakeTime) {
                $this->wakeUpAt($data);
                continue;
            }
            try {
                $this->consume($data);
            } catch (Exception $e) {
                $ex = new ChildException($e);
                $exception = serialize($ex);
                fwrite(STDERR, $exception);
                exit(1);
            }
        }
    }

    /**
     * When a stream event is received, the consume method is called.
     * @param mixed $data
     * @return mixed
     */
    abstract function consume($data);
}