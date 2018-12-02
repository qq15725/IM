<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \IM\Application(
    dirname(__DIR__)
);

$app->IM->start();