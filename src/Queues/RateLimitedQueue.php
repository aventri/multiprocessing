<?php

namespace aventri\ProcOpenMultiprocessing\Queues;

use DateInterval;
use DateTime;
use SplQueue;

class RateLimitedQueue extends WorkQueue
{
    /**
     * @var DateInterval
     */
    private $timeFrame;
    /**
     * @var SplQueue
     */
    private $timeQueue;
    /**
     * @var int
     */
    private $numAllowed;

    public function __construct(DateInterval $timeFrame, $numAllowed = 1, $jobData = array())
    {
        $this->timeFrame = $timeFrame;
        $this->numAllowed = $numAllowed;
        $this->timeQueue = new SplQueue();
        parent::__construct($jobData);
    }

    public function dequeue()
    {
        if ($this->timeQueue->count() === $this->numAllowed) {
            $now = new DateTime();
            /** @var DateTime $start */
            $start = $this->timeQueue->bottom();
            $end = $start->add($this->timeFrame);
            $diff = $end->getTimestamp() - $now->getTimestamp();
            if ($diff > 0) {
                return null;
            }
            $this->timeQueue->dequeue();
        }

        $this->timeQueue->enqueue(new DateTime());
        return parent::dequeue();
    }
}