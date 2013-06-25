<?php

use Queue\QueueManager;
use Applications\DeployManager;
use Applications\ApplicationManager;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Description of DeployWorker
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeployWorker
{

	/** @var QueueManager */
	private $qm;

	/** @var DeployManager */
	private $deployManager;

	/** @var ApplicationManager */
	private $applicationManager;

	/** @var ConsoleOutput */
	private $output;

	public function __construct(QueueManager $qm, DeployManager $deployManager, ApplicationManager $applicationManager, ConsoleOutput $output)
	{
		$this->qm = $qm;
		$this->deployManager = $deployManager;
		$this->applicationManager = $applicationManager;
		$this->output = $output;
	}

	public function consume($queue)
	{
		while(true) {
			sleep(1);
			$message = $this->qm->getMessage($queue);
			if($message !== NULL) {
				$data = json_decode($message->getPayload(), $asArray = TRUE);
				$application = $this->applicationManager->loadApplication($data['applicationId']);
				$branch = $data['branch'];
				$commit = $data['commit'];
				$userId = $data['userId'];
				
				$message = sprintf('Deploying %s, %s:%s', $application->getName(), $branch, $commit);
				$this->output->writeln($message);
				$this->deployManager->deployManual($application, $branch, $commit);
			}
		}
	}
}

