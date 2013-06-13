<?php

namespace Events\Application;

use Symfony\Component\EventDispatcher\Event;

/**
 * Description of ApplicationCreatedEvent
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class ApplicationCreatedEvent extends Event
{

	/** @var \Application */
	private $application;


	/**
	 * @param \Application $application
	 */
	public function __construct(\Application $application)
	{
		$this->application = $application;
	}


	/**
	 * @return \Application
	 */
	public function getApplication()
	{
		return $this->application;
	}


}

