<?php /** @noinspection PhpComposerExtensionStubsInspection */




while(true) {
    $pid = getmypid();
    $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
    $result = socket_connect($socket, "./my.sock");
    socket_write($socket, $pid, strlen($pid));
    socket_close($socket);
}