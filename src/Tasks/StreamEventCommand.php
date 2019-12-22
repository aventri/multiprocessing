<?php

namespace aventri\Multiprocessing\Tasks;

use aventri\Multiprocessing\Exceptions\ChildErrorException;
use aventri\Multiprocessing\Exceptions\ChildException;
use DateTime;
use \Exception;

/**
 * Inside a process script, StreamEventCommand interacts with the Pool through streams.
 * @package aventri\Multiprocessing;
 */
abstract class StreamEventCommand extends EventCommand
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
        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, 0);
        stream_set_blocking(STDERR, 0);
        stream_set_timeout($stdin, 10);
        $this->setupErrorHandler();

        while (true) {
            $read = array($stdin);
            $write = NULL;
            $except = NULL;
            stream_select($read, $write, $except, null);
            $buffer = "";
            while($f = stream_get_contents($stdin)) {
                $buffer .= $f;
            }
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