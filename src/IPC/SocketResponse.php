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


}