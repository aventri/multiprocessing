<?php

namespace aventri\Multiprocessing\ProcessPool;

use InvalidArgumentException;

class StreamPoolPipeline extends PoolPipeline
{
    public function start()
    {
        $allSize = 0;
        foreach ($this->procWorkerPools as $pool) {
            $allSize += $pool->getWorkQueue()->count();
            $allSize += $pool->getRunningJobs();
        }

        while ($allSize > 0) {
            $allSize = 0;
            foreach ($this->procWorkerPools as $pool) {
                $allSize += $pool->getWorkQueue()->count();
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

    protected function process()
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
                if (!is_null($data)) {
                    if (isset($this->procWorkerPools[$whichPool + 1])) {
                        $this->procWorkerPools[$whichPool + 1]->getWorkQueue()->enqueue($data);
                    }
                }
            }
        }
        foreach ($this->procWorkerPools as $pool) {
            $pool->closeShouldClose();
        }
    }
}