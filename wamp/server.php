<?php

use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;
use Ratchet\Session\SessionProvider;
use Nette\Application\Routers\Route;

$configurator = require __DIR__ . '/../app/bootstrap.php';
$container = $configurator->createContainer();

$port = $container->parameters['wamp']['port'];

echo 'Starting server on port: '.$port."\n";

//workaraound for kdyby redis panel
Nette\Diagnostics\Debugger::getBlueScreen();
Nette\Diagnostics\Debugger::getBar();

// Make sure to run as root
$wsServer = new WsServer(new WampServer($container->getService('wsServer')));
$server = IoServer::factory($wsServer, $port);
$server->run();