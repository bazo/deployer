<?php

namespace WAMP;

use Ratchet\Wamp\WampServerInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ratchet\Wamp\Topic;
use Queue\QueueManager;
use Applications\DeployProgress;

/**
 * Description of Server
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class Server implements WampServerInterface
{
	/** @var ConsoleOutput */
	private $output;
	
	/** @var QueueManager */
	private $qm;
	
	/** @var DeployProgress */
	private $deployProgress;
	
	/** @var \SplObjectStorage */
	private $clients;
	
	/** @var \SplObjectStorage */
	private $topics;
	
	public function __construct(ConsoleOutput $output, QueueManager $qm, DeployProgress $deployProgress)
	{
		$this->output = $output;
		$this->qm = $qm;
		$this->deployProgress = $deployProgress;
		
		$this->clients = new \SplObjectStorage;
		$this->topics = new \SplObjectStorage;
	}

	/**
	 * @param \Ratchet\ConnectionInterface $connection
	 */
	public function onOpen(ConnectionInterface $connection)
	{
		$this->clients->attach($connection);
		$this->output->writeln('client connected');
	}


	/**
	 * @param \Ratchet\ConnectionInterface $connection
	 */
	public function onClose(ConnectionInterface $connection)
	{
		$this->clients->detach($connection);
		$this->output->writeln('client closed connection: ');
	}

	/**
	 * @param \Ratchet\ConnectionInterface $connection
	 * @param \Exception $e
	 */
	public function onError(ConnectionInterface $connection, \Exception $e)
	{
		dump(__METHOD__, func_get_args());
	}


	public function onCall(ConnectionInterface $connection, $id, $topic, array $params)
	{
		if($topic->getId() === 'deploy') {
			
			$applicationId = $params['applicationId'];
			$this->output->writeln(sprintf('Deploy for application %s requested.', $applicationId));
			
			if(!$this->deployProgress->isDeployRunning($applicationId)) {
				$message = new \Queue\Message(json_encode($params));
				$this->qm->publishMessage('deploy', $message);
			
				$topic->broadcast('cicina-broadcast');
				$connection->callResult($id, ['cicina-result-id']);
				$this->output->writeln(sprintf('Deploy for application %s allowed.', $applicationId));
			} else {
				$response = [
					'status' => 'error',
					'message' => 'deploy in progress'
				];
				$connection->callResult($id, $response);
				$this->output->writeln(sprintf('Deploy for application %s denied. Deploy in progress.', $applicationId));
			}
		}
	}


	public function onPublish(ConnectionInterface $connection, $topic, $event, array $exclude, array $eligible)
	{
		$topic->broadcast($event);
	}


	public function onSubscribe(ConnectionInterface $connection, $topic)
	{
		if(!$this->topics->contains($topic)) {
			$this->output->writeln('client attached to topic:'.$topic);
			$this->topics->attach($topic);
		}
		
		if(!$topic->has($connection)) {
			$topic->add($connection);
		}
	}


	public function onUnSubscribe(ConnectionInterface $connection, $topic)
	{
		if($topic->has($connection)) {
			$topic->remove($connection);
		}
		
		if($topic->count() === 0) {
			$this->topics->detach($topic);
		}
	}
}

