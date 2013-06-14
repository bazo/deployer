<?php

namespace Commander;

use Applications\ApplicationManager;
use Git\GitManager;
use Applications\DeployManager;
use Nette\Application\UI\Form;
use Symfony\Component\Filesystem\Exception\IOException;
use GitWrapper\GitException;

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

	/** @persistent */
	public $id;

	public function __construct(ApplicationManager $applicationManager, GitManager $gitManager, DeployManager $deployManager)
	{
		parent::__construct($applicationManager);
		$this->gitManager = $gitManager;
		$this->deployManager = $deployManager;
	}


	protected function startup()
	{
		parent::startup();
		$this->repositoriesPath = $this->context->parameters['git']['repositories']['path'];
		$id = $this->getParameter('id');
		$this->application = $this->applicationManager->loadApplication($id);
	}


	public function actionSettings($id)
	{
		
		$branchValues = ['master' => 'master'];
		try {
			$branches = $this->gitManager->loadBranches($this->application);
			foreach ($branches as $branch) {
				$branchValues[$branch] = $branch;
			}
		} catch (\GitWrapper\GitException $e) {
			
		}
		$this['formSettings']['auto_deploy_branch']->setItems($branchValues);
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


}


