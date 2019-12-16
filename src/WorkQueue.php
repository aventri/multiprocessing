<?php

namespace aventri\ProcOpenMultiprocessing;

use SplQueue;

/**
 * SplQueue is a very fast way of using shift to pull items from the front of a queue. (fifo)
 * However; SplQueue uses a double linked list as it's underlying data structure which does not have a fast "count" method.
 * The WorkQueue class requires fast access to the queue size. We keep track of Queue size by overriding enqueue and dequeue.
 * @package aventri\Multiprocessing;
 */
class WorkQueue extends SplQueue
{
    /**
     * @var int
     */
    private $size = 0;

    /**
     * WorkQueue constructor.
     * @param array $jobData
     */
    public function __construct($jobData = array())
    {
        foreach ($jobData as $item) {
            $this->enqueue($item);
        }
    }

    /**
     * @param mixed $value
     */
    public function enqueue($value)
    {
        $this->size++;
        parent::enqueue($value);
    }

    /**
     * @return mixed
     */
    public function dequeue()
    {
        $this->size--;
        return parent::dequeue();
    }

    /**
     * @return int
     */
    public function size()
    {
        return $this->size;
    }
}