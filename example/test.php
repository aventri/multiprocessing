<?php /** @noinspection PhpComposerExtensionStubsInspection */

$socketFile = "./my.sock";
if (file_exists($socketFile)) {
    unlink($socketFile);
}
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
$result = socket_bind($socket, "./my.sock");
$result = socket_listen($socket, 3);


while (true) {
    $spawn = socket_accept($socket);
    $input = socket_read($spawn, 1024);
    echo $input . PHP_EOL;
}
