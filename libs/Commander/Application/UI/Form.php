<?php

namespace Commander\Application\UI\Form;

use Nette\Application\UI\Form as BaseForm;
use Kdyby\BootstrapFormRenderer\BootstrapRenderer

;
/**
 * Description of Form
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class Form extends BaseForm
{
	public function __construct(\Nette\ComponentModel\IContainer $parent = NULL, $name = NULL)
	{
		parent::__construct($parent, $name);
		$this->setRenderer(new BootstrapRenderer);
		
	}
}

