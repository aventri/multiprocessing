<?php

namespace aventri\Multiprocessing\Queues;

use aventri\Multiprocessing\IPC\WakeTime;
use DateInterval;
use DateTime;
use InvalidArgumentException;
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

    public function __construct($numAllowed, DateInterval $timeFrame, $jobData = array())
    {
        if (!is_int($numAllowed)){
            throw new InvalidArgumentException("missing num allowed");
        }
        $this->timeFrame = $timeFrame;
        $this->numAllowed = $numAllowed;
        $this->timeQueue = new SplQueue();
        parent::__construct($jobData);
    }

    public function getWakeTime()
    {
        /** @var DateTime $start */
        $start = clone $this->timeQueue->bottom();
        $end = $start->add($this->timeFrame);
        $wake = new WakeTime();
        $wake->setTime($end->getTimestamp());
        return $wake;
    }

    public function dequeue()
    {
        if ($this->timeQueue->count() === $this->numAllowed) {
            $now = new DateTime();
            /** @var DateTime $start */
            $start = clone $this->timeQueue->bottom();
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