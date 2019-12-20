<?php

namespace aventri\ProcOpenMultiprocessing;

use aventri\ProcOpenMultiprocessing\Exceptions\ChildErrorException;
use aventri\ProcOpenMultiprocessing\Exceptions\ChildException;
use DateTime;
use \Exception;

/**
 * Inside a process script, StreamEventCommand interacts with the WorkerPool through streams.
 * @package aventri\ProcOpenMultiprocessing;
 */
abstract class StreamEventCommand
{
    const DEATH_SIGNAL = "F3FB149707E0B5B61C86DBC012DF5EC0";
    /**
     * @var callable|null
     */
    private $oldErrorHandler;

    private function setupErrorHandler()
    {
        $errorHandler = function($severity, $message, $file, $line) {
            $exception = serialize(new ChildErrorException($message, 0, $severity, $file, $line));
            fwrite(STDERR, $exception);
            exit(1);
        };
        $this->oldErrorHandler = set_error_handler($errorHandler, E_ALL);
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

    private final function wakeUpAt(WakeTime $time)
    {
        $now = new DateTime();
        if($time->getTime() > $now->getTimestamp() + 2) {
            time_sleep_until($time->getTime());
        }
        $this->write($time);
    }

    /**
     * When a stream event is received, the consume method is called.
     * @param mixed $data
     * @return mixed
     */
    abstract function consume($data);

    /**
     * Writes the data to the output stream.
     * @param mixed $data
     */
    public function write($data)
    {
        echo serialize($data);
    }
}