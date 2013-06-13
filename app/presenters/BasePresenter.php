<?php

namespace Commander;

use Nette;


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
}
