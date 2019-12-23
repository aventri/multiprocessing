<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace aventri\Multiprocessing;

use aventri\Multiprocessing\Exceptions\SocketException;

abstract class Mp
{
    /**
     * @param $filename
     * @param $lineNumber
     * @throws SocketException
     */
    protected function throwSocketException($filename, $lineNumber)
    {
        $code = socket_last_error();
        $message = socket_strerror($code);
        throw new SocketException($message, $code, 1, $filename, $lineNumber);
    }
}