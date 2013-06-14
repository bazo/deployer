<?php

namespace Git;

use Symfony\Component\Filesystem\Filesystem;
use GitWrapper\GitWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Description of ApplicationManager
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class GitManager implements EventSubscriberInterface
{

	/** @var Filesystem */
	private $fs;
	private $git;

	/** @var string */
	private $repositoriesPath;

	/** @var string */
	private $hookTemplatesPath;


	public function __construct($repositoriesPath, $hookTemplatesPath, Filesystem $fs, GitWrapper $git)
	{
		$this->repositoriesPath = $repositoriesPath;
		$this->hookTemplatesPath = $hookTemplatesPath;
		$this->fs = $fs;
		$this->git = $git;
	}


	public static function getSubscribedEvents()
	{
		return [
			\Events\ApplicationEvents::APPLICATION_CREATED => [
				['onApplicationCreated', 0],
			],
		];
	}


	public function onApplicationCreated(\Events\Application\ApplicationCreatedEvent $event)
	{
		$application = $event->getApplication();
		$this->createRepository($application);
	}


	public function createRepository(\Application $application)
	{
		$path = $this->formatRepositoryPath($application);
		if ($this->fs->exists($path)) {
			throw new ExistingRepositoryException(sprintf('Repository for application %s already exist', $application->getName()));
		}
		$this->fs->mkdir($path);

		$repo = $this->git->workingCopy($path);
		$repo->init(['shared' => true, 'bare' => true]);

		$this->copyHooks($path);
	}


	public function updateHooks(\Application $application)
	{
		$path = $this->formatRepositoryPath($application);
		$this->copyHooks($path);
	}


	private function copyHooks($path)
	{
		$hooks = ['post-receive', 'pre-receive'];
		foreach ($hooks as $hook) {
			$this->fs->copy($this->hookTemplatesPath . '/' . $hook, $path . '/hooks/' . $hook);
			$this->fs->chmod($path . '/hooks/' . $hook, 0744);
		}
	}


	public function loadBranches(\Application $application)
	{
		$path = $this->formatRepositoryPath($application);
		$repository = $this->git->workingCopy($path);

		$branches = $repository->getBranches();
		return $branches->fetchLocalBranches();
	}

	public function loadCommits(\Application $application, $branch = 'master')
	{
		$path = $this->formatRepositoryPath($application);
		$repository = $this->git->workingCopy($path);
		
		//goto is used because there can be an error while executing log command, git gc should fix it
		log:
			//we run the command explicitely due to windows bug, % gets escaped to space
			$output = [];
			$returnVar = NULL;
			$format = '--pretty=format:"hash=%H&author_name=%an&author_email=%ae&time_relative=%ar&timestamp=%at&message=%s"';
			$command = sprintf('git log %s %s', $format, $branch);
			chdir($path);
			exec($command, $output, $returnVar);
			if($returnVar !== 0) {
				exec('git gc'); //maybe some loose objects
				goto log;
			}
		return LogParser::parseLines($output);
	}

	private function formatRepositoryPath(\Application $application)
	{
		return $this->repositoriesPath . '/' . $application->getRepoName();
	}
	
	
	public function getActiveBranch(\Application $application)
	{
		return '';
	}


}


