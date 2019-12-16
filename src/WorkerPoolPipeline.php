<?php

namespace aventri\ProcOpenMultiprocessing;

use InvalidArgumentException;

class WorkerPoolPipeline
{
    /**
     * @var WorkerPool[]
     */
    private $procWorkerPools;

    public function __construct($pools = array())
    {
        foreach ($pools as $pool) {
            if (!($pool instanceof WorkerPool)) {
                throw new InvalidArgumentException("PoolStream accepts a list of WorkerPool instances");
            }
        }
        $this->procWorkerPools = $pools;
    }

    public function start()
    {
        $allSize = 0;
        foreach ($this->procWorkerPools as $pool) {
            $allSize += $pool->getWorkQueue()->size();
            $allSize += $pool->getRunningJobs();
        }

        while ($allSize > 0) {
            $allSize = 0;
            foreach ($this->procWorkerPools as $pool) {
                $allSize += $pool->getWorkQueue()->size();
                $allSize += $pool->getRunningJobs();
                $pool->createProcs();
                $pool->sendJobs();
            }
            if ($allSize > 0) {
                $this->process();
            }
        }
        return $this->procWorkerPools[count($this->procWorkerPools) - 1]->getCollected();
    }

    private function process()
    {
        $poolIndex = array();
        $stdIndex = array();
        $streams = array();
        foreach ($this->procWorkerPools as $pool) {
            $stdOutPipes = $pool->getStdoutPipes();
            $stdErrPipes = $pool->getStderrPipes();
            $poolStreams = array_merge($stdOutPipes, $stdErrPipes);
            $streams = array_merge($streams, $poolStreams);
            $poolIndex[] = count($streams) - 1;
            $stdIndex[] = array($stdOutPipes, $stdErrPipes);
        }
        $read = $streams;
        $write = null;
        $except = null;
        stream_select($read, $write, $except, null);
        foreach ($read as $r) {
            $id = array_search($r, $streams);
            $whichPool = 0;
            $poolStart = 0;
            for ($i = 0; $i < count($poolIndex); $i++) {
                if ($id <= $poolIndex[$i]) {
                    $whichPool = $i;
                    if (isset($poolIndex[$i - 1])) {
                        $poolStart = $poolIndex[$i - 1] + 1;
                    }
                    break;
                }
            }
            $procId = $id - $poolStart;
            if ($id > ($poolStart + count($stdIndex[$whichPool][0]))) {
                $this->procWorkerPools[$whichPool]->stdErr($procId);
            } else {
                $data = $this->procWorkerPools[$whichPool]->stdOut($procId);
                if (isset($this->procWorkerPools[$whichPool + 1])) {
                    $this->procWorkerPools[$whichPool + 1]->getWorkQueue()->enqueue($data);
                }
            }
        }
        foreach ($this->procWorkerPools as $pool) {
            $pool->closeShouldClose();
        }
    }
}