<?php

namespace Deployer;

use Nette;
use Nette\Utils\ArrayHash;



/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

	public function flash($message, $type = 'success')
	{
		$this->flashMessage($message, $type);
		$this->invalidateControl('flashes');
	}


	protected function beforeRender()
	{
		parent::beforeRender();
		$this->template->ui = ArrayHash::from($this->context->parameters['ui']);
		$this->template->wamp = $this->context->parameters['wamp'];
	}


}
