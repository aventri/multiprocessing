<?php

namespace aventri\Multiprocessing\Pool;

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
                $new = $pool->createProcs();
                $pool->initializeNewProcs($new);
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
        $streams = array();
        /** @var StreamPool $pool */
        foreach ($this->procWorkerPools as $pool) {
            $readPipes = $pool->getReadPipes();
            $streams = array_merge($streams, $readPipes);
            $poolIndex[] = count($streams) - 1;
        }
        $read = $streams;
        $write = null;
        $except = null;
        stream_select($read, $write, $except, null);
        foreach ($read as $r) {
            $id = array_search($r, $streams);
            $whichPool = 0;
            for ($i = 0; $i < count($poolIndex); $i++) {
                if ($id <= $poolIndex[$i]) {
                    $whichPool = $i;
                    break;
                }
            }
            $pool = $this->procWorkerPools[$whichPool];
            $data = $pool->dataReceived($r);
            if (!is_null($data)) {
                if (!is_null($data)) {
                    if (isset($this->procWorkerPools[$whichPool + 1])) {
                        $this->procWorkerPools[$whichPool + 1]->getWorkQueue()->enqueue($data);
                    }
                }
            }
        }
    }
}