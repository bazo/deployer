<?php

namespace Commander;

/**
 * Applications presenter.
 */
class ApplicationsPresenter extends SecuredPresenter
{

	private $repositoriesPath;


	protected function startup()
	{
		parent::startup();
		$this->repositoriesPath = $this->context->parameters['git']['repositories']['path'];
	}


	public function renderDefault()
	{
		$applications = $this->applicationManager->listApplications();
		$this->template->applications = $applications;
	}


	protected function beforeRender()
	{
		parent::beforeRender();

		$this->template->registerHelper('repoPath', callback($this, 'formatRepositoryName'));
	}


	public function formatRepositoryName(\Application $application)
	{
		return $_SERVER['SERVER_NAME'] . ':' . realpath($this->repositoriesPath . '/' . $application->getRepoName());
	}


}


