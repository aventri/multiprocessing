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

##8 Process Fibonacci Example:

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
---
##More Examples:

| Main Script | Workers |
| ---         | --- | 
[Simple Proc Pool]|[Fibo Proc]
[WorkerPool Pipeline]|[Step 1 Fibo] -> [Step 2 Waste Time] -> [Step 3 Waste More Time]
[AlphaVantage API - Draw Stock Charts Pipeline]|[Download Stock Data] -> [Draw Stock Charts]
[Fibo Rate Limited]|[Fibo Proc]
[Rate Limited Pipeline]|[Step 1 Fibo] -> [Step 2 Waste Time] -> [Step 3 Waste More Time]


[Simple Proc Pool]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/simple_proc_pool_example.php>
[WorkerPool Pipeline]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/multi_worker_pool_stream.php>
[AlphaVantage API - Draw Stock Charts Pipeline]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/alpha_vantage_charts_stream.php>
[Fibo Rate Limited]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/rate_limited_example.php>
[Rate Limited Pipeline]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/rate_limited_pipeline.php>
[Fibo Proc]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/fibo_proc.php>  
[Step 1 Fibo]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/pipeline_1_step1.php>
[Step 2 Waste Time]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/pipeline_1_step2.php>
[Step 3 Waste More Time]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/pipeline_1_step3.php>
[Download Stock Data]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/pipeline_2_step1.php>
[Draw Stock Charts]: <https://github.com/aventri/proc-open-multiprocessing/blob/master/example/proc_scripts/pipeline_2_step2.php>