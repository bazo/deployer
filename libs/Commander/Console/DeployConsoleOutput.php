<?php

namespace Commander\Console;

use Symfony\Component\Console\Output\ConsoleOutput as SymfonyConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Events\ConsoleEvents;

/**
 * Description of EventedConsoleOutput
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class DeployConsoleOutput extends SymfonyConsoleOutput
{

	/** @var EventDispatcher */
	private $eventDispatcher;

	/** @var string */
	private $outputBuffer = '';

	private $applicationId;

	public function __construct(EventDispatcher $eventDispatcher, $verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
	{
		parent::__construct($verbosity, $decorated, $formatter);
		$this->eventDispatcher = $eventDispatcher;
	}

	
	/**
	 * @param string $applicationId
	 * @return DeployConsoleOutput
	 */
	public function setApplicationId($applicationId)
	{
		$this->applicationId = $applicationId;
		$this->outputBuffer = '';
		return $this;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function doWrite($message, $newline)
	{
		$line = $message . ($newline ? PHP_EOL : '');
		$this->outputBuffer .= $line;
		
		$event = new \Events\Console\MessageWrite($this->applicationId, $line);
		$this->eventDispatcher->dispatch(ConsoleEvents::MESSAGE_WRITE, $event);
		
		parent::doWrite($message, $newline);
	}


	public function getOutputBuffer()
	{
		return $this->outputBuffer;
	}


}

