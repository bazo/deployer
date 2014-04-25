<?php

namespace Queue;

/**
 * Description of Message
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class Message
{

	/** @var int */
	private $timestamp;

	/** @var string */
	private $payload;


	/**
	 * @param string $payload
	 */
	function __construct($payload)
	{
		$this->payload = $payload;
	}


	/**
	 * @return string
	 */
	public function getPayload()
	{
		return $this->payload;
	}


}

