<?php

namespace Deployer;

use Commander\Application\UI\Form\Form;

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