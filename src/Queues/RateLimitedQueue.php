<?php

namespace aventri\ProcOpenMultiprocessing\Queues;

use DateInterval;

class RateLimitedQueue extends WorkQueue
{
    /**
     * @var DateInterval
     */
    private DateInterval $timeFrame;

    private $timeQueue = array();

    public function __construct($numAllowed, DateInterval $timeFrame, $jobData = array())
    {
        $this->timeFrame = $timeFrame;
        parent::__construct($jobData);
    }

    public function dequeue()
    {
        return parent::dequeue();
    }
}