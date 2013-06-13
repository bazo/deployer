<?php

namespace Console\Command;

use Symfony\Component\Console;
use Applications\DeployManager;
use Applications\ApplicationManager;

/**
 * Delete cache
 * @author Martin Bažík <martin@bazo.sk>
 */
class GitPostReceiveHook extends Console\Command\Command
{

	/** @var DeployManager */
	private $deployManager;

	/** @var ApplicationManager */
	private $applicationManager;

	/**
	 * @param DeployManager $deployManager
	 * @param ApplicationManager $applicationManager
	 */
	public function inject(DeployManager $deployManager, ApplicationManager $applicationManager)
	{
		$this->deployManager = $deployManager;
		$this->applicationManager = $applicationManager;
	}


	protected function configure()
	{
		$this->setName('hooks:post-receive')
				->addArgument('repository')
				->addArgument('oldrev')
				->addArgument('newrev')
				->addArgument('refname')
				->setDescription('Git post-receive hook');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$repository = $input->getArgument('repository');
		$oldrev = $input->getArgument('oldrev');
		$newrev = $input->getArgument('newrev');
		$refname = $input->getArgument('refname');

		$branch = str_replace('refs/heads/', '', $refname);
		
		$application = $this->applicationManager->loadApplicationByRepoName($repository);
		$this->deployManager->deploy($application, $branch, $newrev);
	}


}

