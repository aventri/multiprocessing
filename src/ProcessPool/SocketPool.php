<?php

namespace aventri\Multiprocessing\ProcessPool;

use aventri\Multiprocessing\Tasks\EventCommand;

class SocketPool extends Pool
{

    /**
     * @inheritDoc
     */
    public function start()
    {
        for ($i = 0; $i < $this->numRetries; $i++) {
            while ($this->retryData->count() > 0) {
                $item = $this->retryData->dequeue();
                $this->workQueue->enqueue($item);
            }
            while ($this->workQueue->count() > 0) {
                $this->createProcs();
                $this->sendJobs();
                $this->process();
            }
            while($this->runningJobs > 0) {
                $this->killFree();
                $this->process();
            }
        }

        for($i = 0; $i < count($this->procs); $i++){
            $this->procs[$i]->tell(serialize(EventCommand::DEATH_SIGNAL));
            $this->closeProc($i);
        }

        $this->resetProcs();

        $this->free = array();

        return $this->collected;
    }

    /**
     * @inheritDoc
     */
    public function sendJobs()
    {
        // TODO: Implement sendJobs() method.
    }

    /**
     * @inheritDoc
     */
    protected function killFree()
    {
        // TODO: Implement killFree() method.
    }

    protected function process()
    {
        // TODO: Implement process() method.
    }
}
