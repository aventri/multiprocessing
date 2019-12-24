<?php

namespace aventri\Multiprocessing\IPC;

final class SocketResponse
{
    /**
     * @var int
     */
    private $procId;
    /**
     * @var int
     */
    private $poolId;
    /**
     * @var mixed
     */
    private $data;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
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