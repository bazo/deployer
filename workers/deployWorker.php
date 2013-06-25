<?php

$container = require_once __DIR__ . '/../app/bootstrap.php';

$worker = $container->getService('deployWorker');
$worker->consume('deploy');