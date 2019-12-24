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
     * @var int
     */
    private $procId;

    /**
     * @var int
     */
    private $poolId;

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

    /**
     * @return int
     */
    public function getProcId()
    {
        return $this->procId;
    }

    /**
     * @param int $procId
     */
    public function setProcId($procId)
    {
        $this->procId = $procId;
    }

    /**
     * @return int
     */
    public function getPoolId()
    {
        return $this->poolId;
    }

    /**
     * @param int $poolId
     */
    public function setPoolId($poolId)
    {
        $this->poolId = $poolId;
    }
}