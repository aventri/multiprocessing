# Multiprocessing

<img src="https://aventri.github.io/proc-open-multiprocessing/logo.svg" alt="Proc Open Multiprocessing" width="30%" align="left"/>
Multiprocessing (MP) is a <strong>powerful</strong>, <strong>simple</strong> and
  <strong>structured</strong> PHP library for multiprocessing and async communication.<br />
  MP relies on streams for communication with sub processes with no requirement on the PCNTL extension.
  On Windows MP requires the PHP sockets extension for inter-process communication.
 

---

<p align="center">
  <a href="https://travis-ci.org/aventri/proc-open-multiprocessing"><img src="https://img.shields.io/travis/aventri/proc-open-multiprocessing/master.svg" alt="Build status" /></a>
  <a href="https://coveralls.io/github/aventri/proc-open-multiprocessing?branch=master"><img src="https://img.shields.io/coveralls/aventri/proc-open-multiprocessing/master.svg" alt="Code coverage" /></a>
  <!--<a href="https://scrutinizer-ci.com/g/aventri/proc-open-multiprocessing/?branch=master"><img src="https://scrutinizer-ci.com/g/aventri/proc-open-multiprocessing/badges/quality-score.png?b=master" /></a>-->
  <a href="https://packagist.org/packages/aventri/proc-open-multiprocessing"><img src="https://img.shields.io/packagist/dt/aventri/proc-open-multiprocessing.svg" alt="Packagist" /></a>  
</p>

### Features
* Stream based or Socket based multiprocessing using `worker` scripts
* Auto process scaling with `queues`
* `Pipeline` processing with auto scaled steps
* Non blocking `rate limited queues` 
* Simplified child script `debugging`

### Other Benefits
* Works with PHP 5.3+
* No PCNTL requirement
* Re uses processes for efficiency 
* Parent process receives child stack trace in asynchronous error handler


## 8 Process Fibonacci Example:

1. Create a child process script `fibo.php` using the **Task** class.
    ```php
    (new class extends Task
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
2. Use the `PoolFactory` class to create a Pool instance with 8 workers.
    ```php
    $collected = (PoolFactory::create([
        "task" => "php fibo.php",
        "queue" => new WorkQueue(range(1, 30)),
        "num_processes" => 8,
        "done" => fn($data) => print($data . PHP_EOL),
        "error" => fn(Exception $e) => print($e->getTraceAsString() . PHP_EOL)
    ]))->start();
    ```
---
## More Examples:

| Main Script | Workers |
| ---         | --- | 
[Simple Proc Pool]|[Fibo Proc]
[WorkerPool Pipeline]|[Step 1 Fibo] -> [Step 2 Waste Time] -> [Step 3 Waste More Time]
[AlphaVantage API - Draw Stock Charts Pipeline]|[Download Stock Data] -> [Draw Stock Charts]
[Fibo Rate Limited]|[Fibo Proc]
[Rate Limited Pipeline]|[Step 1 Fibo] -> [Step 2 Waste Time] -> [Step 3 Waste More Time]

## Debugging Child Processes
If you use `xdebug` to debug your php code with a remote interpreter you can debug both the parent and child script by adding adding
the debug line provided by the `Debug` class.

```php 
//create the debug string
$phpCommand = Debug::cli(["remote_port" => 9000, "serverName" => "SomeName"]);
//use this in your command string
$cmd = "$phpCommand fibo.php";
$collected = (PoolFactory::create([
    "task" => $cmd,
    "queue" => new WorkQueue(range(1, 30)),
    "num_processes" => 8
]))->start();
```  
If you are using PHPStorm to debug you can now use the `serverName` as your `server` configuration and set up path mappings.

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
