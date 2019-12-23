<?php

include realpath(__DIR__ . "/../vendor") . "/autoload.php";

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    define("IS_WINDOWS", true);
} else {
    define("IS_WINDOWS", false);
}