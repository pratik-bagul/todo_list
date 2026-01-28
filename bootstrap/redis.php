<?php
// bootstrap/redis.php
require __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

function redisClient(): Client {
    static $client = null;
    if ($client === null) {
        $client = new Client([
            'scheme'   => 'tcp',
            'host'     => '127.0.0.1',
            'port'     => 6379,
            //'password' => 'StrongPass123!', // <-- set your actual password
            'database' => 0,                         // you can change DB index if you want
        ]);
    }
    return $client;
}
