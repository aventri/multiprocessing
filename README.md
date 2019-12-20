# Proc Open Multiprocessing

<img src="https://raw.githubusercontent.com/aventri/proc-open-multiprocessing/master/logo.svg?sanitize=true" alt="Proc Open Multiprocessing" width="30%" align="left"/>
Proc Open Multiprocessing (PM) is a <strong>powerful</strong>, <strong>simple</strong> and
  <strong>structured</strong> PHP library for multiprocessing and async communication using streams.<br /> 
  PM relies on streams for communication with sub processes with no requirement on the PCNTL extension.  
  
<p align="center">
    
</p>

---

<p align="center">
  <a href="https://travis-ci.org/aventri/proc-open-multiprocessing"><img src="https://img.shields.io/travis/aventri/proc-open-multiprocessing/master.svg" alt="Build status" /></a>
  <a href="https://coveralls.io/github/aventri/proc-open-multiprocessing?branch=master"><img src="https://img.shields.io/coveralls/aventri/proc-open-multiprocessing/master.svg" alt="Code coverage" /></a>
  <!--<a href="https://scrutinizer-ci.com/g/aventri/proc-open-multiprocessing/?branch=master"><img src="https://scrutinizer-ci.com/g/aventri/proc-open-multiprocessing/badges/quality-score.png?b=master" /></a>-->
  <a href="https://packagist.org/packages/aventri/proc-open-multiprocessing"><img src="https://img.shields.io/packagist/dt/aventri/proc-open-multiprocessing.svg" alt="Packagist" /></a>  
</p>

8 Process Fibonacci Example:
---------
1. Create a child process script `fibo.php` using the **StreamEventCommand** class.  
    ```php
    (new class extends StreamEventCommand
    {
        private function fibo($number)
        {
            if ($number == 0) {
                return 0;
            } else if ($number == 1) {
                return 1;
            }
    
            return ($this->fibo($number - 1) + $this->fibo($number - 2));
        }
    
        public function consume($number)
        {
            $this->write($this->fibo($number));
        }
    })->listen();
    ```
2. Create a WorkerPool instance with 8 workers.
    ```php
    $collected = (new WorkerPool(
        "php fibo.php",
        new WorkQueue(range(1, 30)),
        [
            "procs" => 8,
            "done" => function($data) {
                echo $data . PHP_EOL;
            },
            "error" => function(Exception $e) {
                echo $e->getTraceAsString() . PHP_EOL;
            }
        ]
    ))->start();
    ```




