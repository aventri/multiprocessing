<?php

namespace aventri\Multiprocessing\Tasks;

use aventri\Multiprocessing\Exceptions\ChildErrorException;
use aventri\Multiprocessing\IPC\Initializer;
use aventri\Multiprocessing\IPC\SocketInitializer;
use aventri\Multiprocessing\IPC\WakeTime;
use DateTime;
use Exception;

abstract class EventTask
{
    const DEATH_SIGNAL = "F3FB149707E0B5B61C86DBC012DF5EC0";
    /**
     * @var callable|null
     */
    private $oldErrorHandler;
    /**
     * @var int
     */
    protected $poolId;
    /**
     * @var int
     */
    protected $procId;

    protected function setupErrorHandler()
    {
        $that = $this;
        $errorHandler = function($severity, $message, $file, $line) use($that) {
            $exception = new ChildErrorException($message, 0, $severity, $file, $line);
            $that->error($exception);
        };
        $this->oldErrorHandler = set_error_handler($errorHandler, E_ALL);
    }

    public abstract function listen();

    public abstract function error(Exception $e);

    public abstract function write($data);

    protected final function wakeUpAt(WakeTime $time)
    {
        $now = new DateTime();
        if($time->getTime() > $now->getTimestamp() + 2) {
            time_sleep_until($time->getTime());
        }
        $this->write($time);
    }
}