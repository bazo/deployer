<?php

namespace Commander;

use Nette\Application\UI\Form;

/**
 * Secured presenter.
 */
class SecuredPresenter extends BasePresenter
{

	/** @var \Applications\ApplicationManager */
	protected $applicationManager;


	/**
	 * @param \Applications\ApplicationManager $applicationManager
	 */
	public function __construct(\Applications\ApplicationManager $applicationManager)
	{
		$this->applicationManager = $applicationManager;
	}


	protected function startup()
	{
		parent::startup();

		if (!$this->user->isLoggedIn()) {
			$this->redirect('sign:in');
		}
	}


	public function actionLogout()
	{
		$this->user->logout($clearIdentity = true);
		$this->redirect('sign:in');
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

		$this->redirect('this');
	}
	

}


