<?php

namespace Console\Command;

use Symfony\Component\Console;
use Applications\DeployManager;
use Applications\ApplicationManager;
use Nette\Caching\IStorage;

/**
 * Git post receive hook
 * @author Martin Bažík <martin@bazo.sk>
 */
class GitPostReceiveHook extends Console\Command\Command
{

	/** @var DeployManager */
	private $deployManager;

	/** @var ApplicationManager */
	private $applicationManager;

	/** @var IStorage */
	private $cacheStorage;
	
	
	/**
	 * @param DeployManager $deployManager
	 * @param ApplicationManager $applicationManager
	 */
	public function inject(DeployManager $deployManager, ApplicationManager $applicationManager, IStorage $cacheStorage)
	{
		$this->deployManager = $deployManager;
		$this->applicationManager = $applicationManager;
		$this->cacheStorage = $cacheStorage;
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
		if($application === NULL) {
			$output->writeln(sprintf('<error>Cannot find application for repository %s</error>', $repository));
			return;
		}
		
		$cache = new \Nette\Caching\Cache($this->cacheStorage, 'commits');
		$key = 'commits-'.$application->getId().'-'.$branch;
		$cache->remove($key);
		
		$this->deployManager->deployAutomatic($application, $branch, $newrev);
	}


}

