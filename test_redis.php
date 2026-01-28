<?php
require 'vendor/autoload.php';

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
    //'password' => 'StrongPass123!', // uncomment if you set one in memurai.conf
]);

$redis->set('name', 'Pratik');
echo $redis->get('name'), PHP_EOL;