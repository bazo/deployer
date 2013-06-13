#!/usr/bin/env php
<?php
//read the git input
$stdin = trim(file_get_contents("php://stdin"));

$args = explode(' ', $stdin);
$oldrev = $args[0];
$newrev = $args[1];
$refname = $args[2];

//find the name of the repository
$repositoryPath = realpath(__DIR__ . '/../');
$repository = basename($repositoryPath);

//change directory to root
$root = realpath(__DIR__ . '/../../../');
chdir($root);
$command = sprintf('php cli hooks:post-receive %s %s %s %s', escapeshellarg($repository), escapeshellarg($oldrev), escapeshellarg($newrev), escapeshellarg($refname));
echo "Executing post receive hook\n";
file_put_contents('command.txt', $command);
$output = [];
exec(escapeshellcmd($command), $output);

foreach ($output as $line) {
	echo $line . "\n";
}