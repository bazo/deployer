<?php

use Nette\Utils\Strings;

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

$debugMode		 = FALSE;
$debugSwitchFile = __DIR__ . '/local/debug';

if (file_exists($debugSwitchFile)) {
	$debugMode = Strings::trim(mb_strtolower(file_get_contents($debugSwitchFile))) === 'true' ? TRUE : FALSE;
}

if (PHP_SAPI !== 'cli') {

	$debugWhitelistFile = __DIR__ . '/local/debug_whitelist';
	if (file_exists($debugWhitelistFile)) {
		$ip			 = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
		$whitelist	 = file($debugWhitelistFile);
		array_walk($whitelist, function(&$ip) {
			$ip = trim($ip);
		});

		if (in_array($ip, $whitelist)) {
			$debugMode = TRUE;
		}
	}
}
// Enable Nette Debugger for error visualisation & logging
$configurator->setDebugMode($debugMode);
$configurator->enableDebugger(__DIR__ . '/../log');

// Specify folder for cache
$configurator->setTempDirectory(__DIR__ . '/../temp');

// Enable RobotLoader - this will load all classes automatically
$configurator->createRobotLoader()
		->addDirectory(__DIR__)
		->addDirectory(__DIR__ . '/../libs')
		->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/app.neon');

$localConfig = __DIR__ . '/config/config.neon';
if (file_exists($localConfig)) {
	$configurator->addConfig($localConfig);
}

$localConfig = __DIR__ . '/local/config.local.neon';

if (file_exists($localConfig)) {
	$configurator->addConfig($localConfig);
}

return $configurator;
