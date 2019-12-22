<?php

namespace aventri\Multiprocessing\Exceptions;

use Exception;
use ReflectionClass;
use Serializable;

class ChildException extends Exception implements Serializable
{
    /**
     * @var array
     */
    private $originalTrace;

    public function __construct(Exception $e)
    {
        $this->message = $e->getMessage();
        $this->code = $e->getCode();
        $this->file = $e->getFile();
        $this->line = $e->getLine();
        $this->originalTrace = $e->getTrace();
        parent::__construct($this->message, $this->code);
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        $newTrace = array();
        $trace = $this->originalTrace;
        foreach ($trace as &$t) {
            $t["args"] = array();
            $newTrace[] = $t;
        }

        return serialize(array(
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