<?php

namespace Commander;

use Nette\Application\UI\Form;

/**
 * Description of SettingsPresenter
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class SettingsPresenter extends SecuredPresenter
{

	protected function createComponentFormSettings()
	{
		$form = new Form;
		$form->addText('wwwRoot', 'wwwRoot');
		return $form;
	}

}