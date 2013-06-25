<?php

namespace Commander;

use Applications\ApplicationManager;
use Git\GitManager;
use Applications\DeployManager;
use Commander\Application\UI\Form\Form;
use Symfony\Component\Filesystem\Exception\IOException;
use GitWrapper\GitException;
use Nette\Caching\IStorage;

/**
 * Applications presenter.
 */
class ApplicationPresenter extends SecuredPresenter
{

	private $repositoriesPath;

	/** @var GitManager */
	private $gitManager;

	/** @var DeployManager */
	private $deployManager;

	/** @var \Application */
	private $application;

	/** @var IStorage */
	private $cacheStorage;
	
	/** @persistent */
	public $id;

	public function __construct(GitManager $gitManager, DeployManager $deployManager, IStorage $cacheStorage)
	{
		$this->gitManager = $gitManager;
		$this->deployManager = $deployManager;
		$this->cacheStorage = $cacheStorage;
	}


	protected function startup()
	{
		parent::startup();
		$this->repositoriesPath = $this->context->parameters['git']['repositories']['path'];
		$id = $this->getParameter('id');
		$this->application = $this->applicationManager->loadApplication($id);
		
		$activeBranch = $this->gitManager->getActiveBranch($this->application);
	}

	
	public function renderRelease($release_id)
	{
		$release = $this->applicationManager->getRelease($this->application, $release_id);
		$this->template->release = $release;
	}
	
	public function renderReleases()
	{
		$releaseHistory = $this->applicationManager->getReleaseHistory($this->application);
		
		$this->template->releaseHistory = $releaseHistory;
	}

	public function renderCommits()
	{
		$branches = $this->gitManager->loadBranches($this->application);
		$selectedBranch = $this->getHttpRequest()->getCookie($this->user->getId().'-branch');
		$selectedBranch = $selectedBranch !== NULL ? $selectedBranch : $branches[0];
		
		$cache = new \Nette\Caching\Cache($this->cacheStorage, 'commits');
		$key = 'commits-'.$this->application->getId().'-'.$selectedBranch;
		
		$commitsByDate = $cache->load($key);
		if($commitsByDate === NULL) {
			$log = $this->gitManager->loadCommits($this->application, $selectedBranch);

			$commitsByDate = [];
			foreach($log as $commit) {
				$date = date('Y-m-d', $commit['timestamp']);
				$commitsByDate[$date][] = $commit;
			}
			
			$cache->save($key, $commitsByDate);
		}
		
		$this->template->commitsByDate = $commitsByDate;
		$this->template->branches = $branches;
		$this->template->selectedBranch = $selectedBranch;
	}
	
	public function actionSettings()
	{
		$branchValues = $this->createBranchValues();
		$this['formSettings']['auto_deploy_branch']->setItems($branchValues);
	}

	private function createBranchValues()
	{
		$branchValues = ['master' => 'master'];
		try {
			$branches = $this->gitManager->loadBranches($this->application);
			foreach ($branches as $branch) {
				$branchValues[$branch] = $branch;
			}
		} catch (\GitWrapper\GitException $e) {
			
		}
		return $branchValues;
	}
	

	public function renderSettings()
	{
		$this['formSettings']->setDefaults($this->application->getSettings());
	}


	protected function beforeRender()
	{
		parent::beforeRender();
		$this->template->application = $this->application;
		$this->template->registerHelper('repoPath', callback($this, 'formatRepositoryName'));
		$this->template->registerHelper('deployData', function(\Application $application, $branch, $commit){
			$data = [
				'applicationId' => $application->getId(),
				'branch' => $branch,
				'commit' => $commit,
				'userId' => $this->getUser()->getId()
			];
			
			return json_encode($data);
		});
	}


	public function formatRepositoryName(\Application $application)
	{
		return $_SERVER['SERVER_NAME'] . ':' . realpath($this->repositoriesPath . '/' . $application->getRepoName());
	}


	protected function createComponentFormSettings()
	{
		$form = new Form;

		$form->addText('deploy_dir', 'Deployment directory')->setRequired();
		$form->addCheckbox('auto_deploy', 'Auto deploy'); //->addCondition(Form::FILLED)->toggle('auto_deploy');
		$form->addSelect('auto_deploy_branch', 'Auto deploy branch');
		$form->addSubmit('btnSubmit');
		$form->onSuccess[] = callback($this, 'formSettingsSuccess');

		return $form;
	}


	public function formSettingsSuccess(Form $form)
	{
		$values = $form->getValues($asArray = TRUE);

		$this->applicationManager->updateSettings($this->application, $values);
		try {
			$this->deployManager->prepareFoldersForDeploy($values['deploy_dir']);
		} catch (\Symfony\Component\Filesystem\Exception\IOException $e) {
			$this->flash('Cannot create deploy folders. Permissions denied', 'error');
		}
		$this->redirect('this');
	}


	public function handleCreateDeployFolders()
	{
		$settings = $this->application->getSettings();
		try {
			$this->deployManager->prepareFoldersForDeploy($settings['deploy_dir']);
			$this->flash('Deploy folders created', 'success');
		} catch (IOException $e) {
			$this->flash('Cannot create deploy folders. Permissions denied', 'error');
		}
		$this->redirect('this');
	}


	public function handleCreateRepository()
	{
		try {
			$this->gitManager->createRepository($this->application);
			$this->flash('Repository created', 'success');
		} catch (\Git\ExistingRepositoryException $e) {
			$this->flash('Repository already exists.', 'error');
		} catch (GitException $e) {
			$this->flash($e->getMessage(), 'error');
		}
		$this->redirect('this');
	}


	public function handleUpdateHooks()
	{
		try {
			$this->gitManager->updateHooks($this->application);
			$this->flash('Hooks updated', 'success');
		} catch (IOException $e) {
			$this->flash($e->getMessage(), 'error');
		}
		$this->redirect('this');
	}
	
	public function handleChangeBranch($branch)
	{
		$this->getHttpResponse()->setCookie($this->user->getId().'-branch', $branch, PHP_INT_MAX); //expire somewhere in far future
		$this->redirect('this');
	}


}


