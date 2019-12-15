<?php

namespace ProcOpenThreading;

class Thread
{
    /**
     * @var resource
     */
    private $pref; // process reference
    /**
     * @var array
     */
    private $pipes; // stdio
    /**
     * @var string
     */
    private $buffer; // output buffer
    /**
     * @var string
     */
    private $output;
    /**
     * @var string
     */
    private $error;
    /**
     * @var int
     */
    private $timeout;
    /**
     * @var int
     */
    private $start_time;
    /**
     * @var string
     */
    private $command;

    /**
     * @param string $command
     * @param int $timeout
     * @return Thread
     */
    static function Create($command, $timeout = 120)
    {
        $t = new Thread($command, $timeout);
        $t->start();
        return $t;
    }

    /**
     * Thread constructor.
     * @param string $command
     * @param int $timeout
     */
    public function __construct($command = "", $timeout = 120)
    {
        $this->pref = 0;
        $this->buffer = "";
        $this->pipes = (array)null;
        $this->output = "";
        $this->error = "";
        $this->start_time = time();
        $this->timeout = $timeout;
        $this->command = $command;
    }

    /**
     * Start the thread
     * @return bool
     */
    public function start()
    {
        $descriptor = array(
            // stdin
            0 => array("pipe", "r"),
            // stdout
            1 => array("pipe", "w"),
            // stderr
            2 => array("pipe", "w")
        );
        //Open the resource to execute $command
        $this->pref = proc_open($this->command, $descriptor, $this->pipes);
        //Set STDOUT and STDERR to non-blocking
        stream_set_blocking($this->pipes[1], 0);
        stream_set_blocking($this->pipes[2], 0);
        return $this->pref != false;
    }

    public function getPref()
    {
        return $this->pref;
    }

    /**
     * @return array
     */
    public function getPipes()
    {
        return $this->pipes;
    }


    /**
     * @return bool
     */
    public function isActive()
    {
        $this->buffer .= $this->listen();
        $f = stream_get_meta_data($this->pipes[1]);

        return !$f["eof"];
    }


    /**
     * @return string
     */
    public function listen()
    {
        $buffer = $this->buffer;
        $this->buffer = "";
        while ($r = stream_get_contents($this->pipes[1])) {
            $buffer .= $r;
        }

        return $buffer;
    }


    /**
     * @return int
     */
    public function close()
    {
        $r = proc_close($this->pref);
        $this->pref = null;

        return $r;
    }


    /**
     * @param $thought
     */
    public function tell($thought)
    {
        fwrite($this->pipes[0], $thought);
    }


    /**
     * @return array|false
     */
    public function getStatus()
    {
        return proc_get_status($this->pref);
    }

    /**
     * @return bool
     */
    public function isBusy()
    {
        return ($this->start_time > 0) && ($this->start_time + $this->timeout < time());
    }

    /**
     * @return string
     */
    public function getError()
    {
        $buffer = "";
        while ($r = fgets($this->pipes[2], 1024)) {
            $buffer .= $r;
        }

        return $buffer;
    }

    /**
     * @return int
     */
    public function getDurationSeconds()
    {
        return time() - $this->start_time;
    }
}