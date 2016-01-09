<?php

namespace Deployer;

use Commander\Application\UI\Form\Form;
use Nette\Utils\Strings;



/**
 * Secured presenter.
 */
class SecuredPresenter extends BasePresenter
{

	/**
	 * @inject
	 * @var \Applications\ApplicationManager
	 */
	public $applicationManager;

	/**
	 * @inject
	 * @var \Applications\DeployProgress
	 */
	public $deployProgress;



	protected function startup()
	{
		parent::startup();

		if (!$this->user->isLoggedIn()) {
			$this->redirect('Sign:In');
		}
	}


	public function actionLogout()
	{
		$this->user->logout($clearIdentity = true);
		$this->redirect('Sign:In');
	}


	protected function createComponentFormAddApplication()
	{
		$form = new Form;

		$form->addText('name', 'Name');
		$form->addSubmit('btnSubmit', 'Add');

		$form->onSuccess[] = callback($this, 'formAddApplicationSuccess');

		return $form;
	}


	public function formAddApplicationSuccess(Form $form)
	{
		$values = $form->getValues();
		$name = $values->name;

		try {
			$this->applicationManager->createApplication($name);
			$this->flash('Application successfully created');
		} catch (\Applications\ExistingApplicationException $e) {
			$this->flash($e->getMessage(), 'error');
		}

		$this->redirect('Applications:');
	}


	protected function beforeRender()
	{
		parent::beforeRender();
		$this->template->registerHelper('gravatar', function($email, $size = 30) {
			$hash = md5(Strings::lower(Strings::trim($email)));
			return sprintf('http://www.gravatar.com/avatar/%s?s=%d', $hash, $size);
		});
		$this->template->deploys = $this->deployProgress->listDeploys();
	}


}
