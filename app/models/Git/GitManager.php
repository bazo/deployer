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
		if($this->fs->exists($path)) {
			throw new ExistingRepositoryException(sprintf('Repository for application %s already exist', $application->getName()));
		}
		$this->fs->mkdir($path);

		$repo = $this->git->workingCopy($path);
		$repo->init(['shared' => true, 'bare' => true]);
		
		$this->fs->copy($this->hookTemplatesPath.'/post-receive', $path.'/hooks/post-receive');
		$this->fs->chmod($path.'/hooks/post-receive', 0744);
	}
	
	public function loadBranches(\Application $application)
	{
		$path = $this->formatRepositoryPath($application);
		$repository = $this->git->workingCopy($path);
		
		$branches = $repository->getBranches();
		return $branches->fetchLocalBranches();
	}
	
	private function formatRepositoryPath(\Application $application)
	{
		return $this->repositoriesPath . '/' . $application->getRepoName();
	}
}
