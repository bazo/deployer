<?php

namespace Applications;

use Kdyby\Redis\RedisClient;
use \Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WAMP\WAMPClient;



/**
 * Description of DeployProgress
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeployProgress implements EventSubscriberInterface
{

	/** @var RedisClient */
	private $redis;

	/** @var WAMPClient */
	private $wampClient;

	public function __construct(RedisClient $redis, WAMPClient $wampClient)
	{
		$this->redis		 = $redis;
		$this->wampClient	 = $wampClient;
	}


	public static function getSubscribedEvents()
	{
		return [
			\Events\ApplicationEvents::DEPLOY_STARTED	 => [
				['onDeployStarted', 0]
			],
			\Events\ApplicationEvents::DEPLOY_FINISHED	 => [
				['onDeployFinished', 0]
			],
			\Events\ConsoleEvents::MESSAGE_WRITE		 => [
				['onMessageWrite', 0]
			],
		];
	}


	private function connectWamp()
	{
		static $connected = FALSE;
		if (!$connected) {
			try {
				$this->wampClient->connect();
				$connected = TRUE;
			} catch (\RuntimeException $e) {
				//connection failed
			}
		}
	}


	public function onDeployStarted(\Events\Application\DeployStarted $event)
	{
		$applicationId = $event->getApplicationId();
		$this->startDeploy($applicationId);
	}


	public function onDeployFinished(\Events\Application\DeployFinished $event)
	{
		$applicationId = $event->getApplicationId();
		$this->finishDeploy($applicationId);
	}


	public function onMessageWrite(\Events\Console\MessageWrite $event)
	{
		$applicationId	 = $event->getApplicationId();
		$message		 = $event->getMessage();
		$this->addMessage($applicationId, $message);
	}


	public function startDeploy($applicationId)
	{
		$this->connectWamp();
		$payload = [
			'applicationId' => $applicationId
		];
		$this->wampClient->publish('deploy-start', $payload);
		$this->redis->sadd('deploys', $applicationId);
		return $this;
	}


	public function addMessage($applicationId, $message)
	{
		$this->redis->rPush('messages:' . $applicationId, $message);
		return $this;
	}


	public function isDeployRunning($applicationId)
	{
		return false;
		return $this->redis->sIsMember('deploys', $applicationId);
	}


	public function listDeploys()
	{
		$deploys	 = $this->redis->sMembers('deploys');
		$messages	 = [];
		$this->redis->multi();
		foreach ($deploys as $applicationId) {
			$this->redis->lRange('messages:' . $applicationId, 0, -1);
		}
		$response = $this->redis->exec();

		foreach ($deploys as $index => $applicationId) {
			$messages[$applicationId] = $response[$index];
		}

		return $messages;
	}


	public function clearDeploys()
	{
		$deploys = $this->redis->sMembers('deploys');
		$this->redis->multi();
		foreach ($deploys as $applicationId) {
			$this->redis->srem('deploys', $applicationId);
			$this->redis->lTrim('messages:' . $applicationId, 1, 0);
		}
		$this->redis->exec();
	}


	public function finishDeploy($applicationId)
	{
		$this->connectWamp();
		$payload = [
			'applicationId' => $applicationId
		];
		$this->wampClient->publish('deploy-finish', $payload);
		$this->redis->multi();
		$this->redis->srem('deploys', $applicationId);
		$this->redis->lTrim('messages:' . $applicationId, 1, 0);
		$this->redis->exec();
	}


}