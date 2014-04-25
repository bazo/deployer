<?php

namespace Events\Console;

use Symfony\Component\EventDispatcher\Event;

/**
 * Description of MessageWrite
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class MessageWrite extends Event
{

	/** @var string */
	private $message;
	private $applicationId;


	public function __construct($applicationId, $message)
	{
		$this->message = $message;
		$this->applicationId = $applicationId;
	}


	public function getMessage()
	{
		return $this->message;
	}


	public function getApplicationId()
	{
		return $this->applicationId;
	}


}

