<?php

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;



/**
 * Description of BaseManager
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class BaseManager
{

	/** @var DocumentManager */
	protected $dm;

	/** @var EventDispatcher */
	protected $mediator;



	/**
	 * @param DocumentManager $dm
	 */
	public function __construct(DocumentManager $dm, EventDispatcher $mediator)
	{
		$this->dm = $dm;
		$this->mediator = $mediator;
	}


}
