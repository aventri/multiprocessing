<?php

namespace aventri\ProcOpenMultiprocessing\Queues;

use DateInterval;

class RateLimitedQueue extends WorkQueue
{
    /**
     * @var DateInterval
     */
    private $timeFrame;

    private $timeQueue = array();
    /**
     * @var int
     */
    private $numAllowed;

    public function __construct(DateInterval $timeFrame, $numAllowed = 1, $jobData = array())
    {
        $this->timeFrame = $timeFrame;
        $this->numAllowed = $numAllowed;
        parent::__construct($jobData);
    }

    public function dequeue()
    {
        return parent::dequeue();
    }
}