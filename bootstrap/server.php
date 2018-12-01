<?php

require_once __DIR__ . '/../vendor/autoload.php';

try {
    (new Dotenv\Dotenv(dirname(__DIR__)))->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    //
}

$app = new \IM\Application(
    dirname(__DIR__)
);

$server = $app->make(Swoole\WebSocket\Server::class);
$server->set($app->config['server.settings']);
$server->on('message', function () {});
$server->on('task', function () {});
$server->on('finish', function () {});
$server->start();