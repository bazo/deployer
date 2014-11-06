<?php

namespace Console\Command;


use Symfony\Component\Console;

/**
 * Install command
 * @author Martin Bažík <martin@bazo.sk>
 */
class Install extends Console\Command\Command
{

	protected function configure()
	{
		$this->setName('app:install')
				->setDescription('Installs application');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$output->writeln('<info>Installing Commander</info>');
		$application = $this->getApplication();

		$commands = [
			$application->get('odm:schema:drop'),
			$application->get('odm:schema:create'),
			$application->get('odm:generate:hydrators'),
			$application->get('odm:generate:proxies'),
		];

		foreach ($commands as $command) {
			$command->run($input, $output);
		}

		$output->writeln('<info>Finished</info>');
	}


}
