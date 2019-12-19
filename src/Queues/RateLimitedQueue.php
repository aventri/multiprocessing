<?php

namespace aventri\ProcOpenMultiprocessing\Queues;

use DateInterval;

class RateLimitedQueue extends WorkQueue
{
    public function __construct(DateInterval $rateLimit, $jobData = array())
    {
        parent::__construct($jobData);
    }
}