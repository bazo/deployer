<?php

$configurator = require_once __DIR__ . '/../app/bootstrap.php';
$container = $configurator->createContainer();
$worker = $container->getService('deployWorker');
$worker->consume('deploy');