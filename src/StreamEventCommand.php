<?php

namespace aventri\ProcOpenMultiprocessing;

use ErrorException;
use \Exception;

/**
 * Inside a process script, StreamEventCommand interacts with the WorkerPool through streams.
 * @package aventri\Multiprocessing;
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
            $exception = serialize(new ErrorException($message, 0, $severity, $file, $line));
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
            while($f = stream_get_contents(STDIN)) {
                $buffer .= $f;
            }
            //don't try to unserialize if we have nothing ready from STDIN, this will save cpu cycles
            if ($buffer === "") {
                exit(0);
            }
            $data = unserialize($buffer);
            if ($data === self::DEATH_SIGNAL) {
                exit(0);
            }
            try {
                $this->consume($data);
            } catch (Exception $e) {
                $exception = serialize($e);
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

    /**
     * Writes the data to the output stream.
     * @param mixed $data
     */
    public function write($data)
    {
        echo serialize($data) . PHP_EOL;
    }
}