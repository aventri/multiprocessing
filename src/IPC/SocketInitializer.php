<?php

namespace aventri\Multiprocessing\IPC;

final class SocketInitializer extends Initializer
{
    /**
     * @var string
     */
    public $unixSocketFile;

    /**
     * @return string
     */
    public function getUnixSocketFile()
    {
        return $this->unixSocketFile;
    }

    /**
     * @param string $unixSocketFile
     */
    public function setUnixSocketFile($unixSocketFile)
    {
        $this->unixSocketFile = $unixSocketFile;
    }
}