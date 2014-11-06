<?php

namespace Console\Command;


use Symfony\Component\Console;

/**
 * Delete cache
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeleteCache extends Console\Command\Command
{

	protected function configure()
	{
		$this->setName('app:cache:delete')
				->setDescription('Deletes cache');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{

		$context = $this->getHelper('containerHelper')->getContainer();

		$tempDir	 = $context->parameters['tempDir'];
		$cacheDir	 = $tempDir . '/cache';

		$output->writeln(sprintf('deleting cache directory %s ', $cacheDir));

		exec(sprintf('rm -r "%s"', $cacheDir));

		$context->eventLog->setConsoleUser();
		$context->eventLog->appendLog("system", "deleteCache", "OK");

		$output->writeln('<info>done</info>');
	}


}
