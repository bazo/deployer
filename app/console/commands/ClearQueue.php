<?php

namespace Console\Command;

use Symfony\Component\Console;
use Queue\QueueManager;
use Applications\DeployProgress;

/**
 * Delete cache
 * @author Martin Bažík <martin@bazo.sk>
 */
class ClearQueue extends Console\Command\Command
{

	/** @var QueueManager */
	private $qm;

	/** @var DeployProgress */
	private $deployProgress;

	public function injectQm(QueueManager $qm, DeployProgress $deployProgress)
	{
		$this->qm = $qm;
		$this->deployProgress = $deployProgress;
		return $this;
	}


	protected function configure()
	{
		$this->setName('queue:clear')
				->setDescription('Clears the deploy queue');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$queue = 'deploy';
		$this->qm->clearQueue($queue);
		
		$this->deployProgress->clearDeploys();
	}


}

