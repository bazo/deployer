<?php

use \Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WAMP\WAMPClient;

/**
 * Description of MessagePublisher
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class MessagePublisher implements EventSubscriberInterface
{

	/** @var WAMPClient */
	private $wampClient;


	public function __construct(WAMPClient $wampClient)
	{
		$this->wampClient = $wampClient;
	}


	public function __destruct()
	{
		$this->wampClient->disconnect();
	}


	public static function getSubscribedEvents()
	{
		return [
			\Events\ConsoleEvents::MESSAGE_WRITE => [
				['onMessageWrite', 0]
			],
		];
	}


	public function onMessageWrite(Events\Console\MessageWrite $event)
	{
		static $connected = FALSE;

		if (!$connected) {
			$this->wampClient->connect();
			$connected = TRUE;
		}

		$applicationId = $event->getApplicationId();
		$message = $event->getMessage();

		$topicUri = 'deploy-progress';
		$payload = [
			'applicationId' => $applicationId,
			'message' => $message
		];

		$this->wampClient->publish($topicUri, $payload);
	}


}

