<?php

namespace Events\Application;

use Symfony\Component\EventDispatcher\Event;

/**
 * Description of Deploy
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeployEvent extends Event
{
	/** @var string */
	protected $applicationId;


	public function __construct($applicationId)
	{
		$this->applicationId = $applicationId;
	}


	public function getApplicationId()
	{
		return $this->applicationId;
	}


}

