<?php

namespace aventri\Multiprocessing\IPC;

final class SocketHead
{
    const HEADER_LENGTH = 400;
    /**
     * @var int
     */
    private $bytes;

    /**
     * @return int
     */
    public function getBytes()
    {
        return $this->bytes;
    }

    /**
     * @param int $bytes
     */
    public function setBytes($bytes)
    {
        $this->bytes = $bytes;
    }

    public static function pad($headerString)
    {
        $headLength = strlen($headerString);
        for($i = 0; $i < SocketHead::HEADER_LENGTH - $headLength; $i++) {
            $headerString .= " ";
        }
        return $headerString;
    }
}