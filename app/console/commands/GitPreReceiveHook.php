<?php

namespace Console\Command;


use Symfony\Component\Console;
use Applications\DeployManager;
use Applications\ApplicationManager;
use Applications\DeployProgress;

/**
 * Git pre recieve hook
 * @author Martin Bažík <martin@bazo.sk>
 */
class GitPreReceiveHook extends Console\Command\Command
{

	/** @var DeployManager */
	private $deployManager;

	/** @var ApplicationManager */
	private $applicationManager;

	/** @var DeployProgress */
	private $deployProgress;

	function __construct(DeployManager $deployManager, ApplicationManager $applicationManager, DeployProgress $deployProgress)
	{
		parent::__construct();
		$this->deployManager		 = $deployManager;
		$this->applicationManager	 = $applicationManager;
		$this->deployProgress		 = $deployProgress;
	}


	protected function configure()
	{
		$this->setName('hooks:pre-receive')
				->addArgument('repository')
				->addArgument('oldrev')
				->addArgument('newrev')
				->addArgument('refname')
				->setDescription('Git post-receive hook');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$repository	 = $input->getArgument('repository');
		$oldrev		 = $input->getArgument('oldrev');
		$newrev		 = $input->getArgument('newrev');
		$refname	 = $input->getArgument('refname');

		$branch = str_replace('refs/heads/', '', $refname);

		$application = $this->applicationManager->loadApplicationByRepoName($repository);
		if ($application === NULL) {
			$output->writeln(sprintf('<error>Cannot find application for repository %s</error>', $repository));
			exit(1);
		}

		if ($this->deployProgress->isDeployRunning($application->getId())) {
			$output->writeln(sprintf('<error>There is a deploy running for this application. Try again later.</error>', $repository));
			exit(1);
		}
	}


}
