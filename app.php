<?php

error_reporting(E_ALL);

$option = getopt('', ['port:']);
$port = (isset($option['port']) && intval($option['port']) > 1)
    ? $option['port']
    : 6379;

echo "Service started\n\n";

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($sock, "localhost", $port);
socket_listen($sock, 5);
socket_set_nonblock($sock);

$connections = [];
$keyValueRepository = [];

while (true) {
    if ($newconnection = socket_accept($sock)) {
        socket_set_nonblock($newconnection);
        $connections[] = $newconnection;
    }

    foreach ($connections as $connection) {
        if ($input = socket_read($connection, 1024)) {
            $output = proccess($input);
            socket_write($connection, $output);
        }
    }
}

socket_close($sock);

function parse($input)
{
    $perLines = explode("\r\n", $input);
    return $perLines;
}

function proccess($userInput)
{
    $input = parse($userInput);

    switch (strtolower($input[2])) {
        case 'ping':
            return "+PONG\r\n";
        case 'echo':
            return "$" . strlen($input[4]) ."\r\n" . $input[4] ."\r\n";
        case 'set':
            return setValue($input);
        case 'get':
            return getValue($input);
        default:
            return "+\r\n";
    }
}

function dump($value)
{
    var_dump($value);
    echo "\n";
}

function setValue($input)
{
    global $keyValueRepository;

    // Set the value on repository
    $keyValueRepository[$input[4]]['value'] = $input[6];

    // Define a expiry date for the value
    if (isset($input[8]) && strtolower($input[8]) == 'px')
        $keyValueRepository[$input[4]]['ttl'] = time() + $input[10];

    return "+OK\r\n";
}

function getValue($input)
{
    global $keyValueRepository;

    // Find the value by the key
    if (isset($keyValueRepository[$input[4]])) {
        $item = $keyValueRepository[$input[4]];
        
        // Verify if the item is expired
        if (isset($item['ttl']) && $item['ttl'] < time()) {
            unset($keyValueRepository[$input[4]]);
            return "$-1\r\n";
        }
        
        return "$" . strlen($item['value']) . "\r\n" . $item['value'] . "\r\n";
    }

    return "$-1\r\n";
}

?>
