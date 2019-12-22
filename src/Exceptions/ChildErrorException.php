<?php

namespace aventri\Multiprocessing\Exceptions;

use ErrorException;
use Exception;
use ReflectionClass;
use Serializable;

class ChildErrorException extends ErrorException implements Serializable
{
    /**
     * @inheritDoc
     */
    public function serialize()
    {
        $newTrace = array();
        $trace = $this->getTrace();
        foreach ($trace as &$t) {
            $t["args"] = array();
            $newTrace[] = $t;
        }

        return serialize(array(
            "severity" => $this->severity,
            "message" => $this->message,
            "code" => $this->code,
            "file" => $this->file,
            "line" => $this->line,
            "originalTrace" => $newTrace
        ));
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $array = unserialize($serialized);
        $this->severity = $array["severity"];
        $this->message = $array["message"];
        $this->code = $array["code"];
        $this->file = $array["file"];
        $this->line = $array["line"];

        $reflectionClass = new ReflectionClass(ChildErrorException::class);
        $errorExceptionParent = $reflectionClass->getParentClass();
        $parentClass = $errorExceptionParent->getParentClass();
        $parentProperty = $parentClass->getProperty("trace");
        $parentProperty->setAccessible(true);
        $parentProperty->setValue($this, $array["originalTrace"]);
    }
}