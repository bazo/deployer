<?php

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Release
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @ODM\Document
 */
class Release
{

	const SUCCESS = 'success';
	const FAIL = 'fail';
	const WARNING = 'warning';


	/**
	 * @var string
	 * @ODM\Id
	 */
	private $id;

	/**
	 * @ODM\Date
	 * @ODM\Index(order="desc")
	 * @var \DateTime
	 */
	private $date;

	/**
	 * @ODM\Field(type="string")
	 * @ODM\Index(order="desc")
	 * @var string
	 */
	private $number;

	/**
	 * @ODM\ReferenceOne(targetDocument="Application")
	 * @var \Application
	 */
	private $application;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	private $status;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	private $branch;

	/**
	 * @ODM\Field(type="string")
	 * @ODM\Index
	 * @var string
	 */
	private $commit;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	private $message;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	private $commitMessage;

	/**
	 * @ODM\Field(type="string")
	 * @var string
	 */
	private $deployOutput;

	/**
	 * @param \Application $application
	 * @param string $branch
	 * @param string $commit
	 */
	public function __construct(\Application $application, $branch, $commit)
	{
		$this->application = $application;
		$this->branch = $branch;
		$this->commit = $commit;

		$this->date = new \DateTime;
		$this->number = $this->date->format('YmdHis');
	}


	public function getId()
	{
		return $this->id;
	}


	public function getDate()
	{
		return $this->date;
	}


	public function getNumber()
	{
		return $this->number;
	}


	public function getApplication()
	{
		return $this->application;
	}


	public function getStatus()
	{
		return $this->status;
	}


	public function getBranch()
	{
		return $this->branch;
	}


	public function getCommit()
	{
		return $this->commit;
	}


	public function getMessage()
	{
		return $this->message;
	}


	public function setCommit($commit)
	{
		$this->commit = $commit;
		return $this;
	}


	public function success()
	{
		$this->status = self::SUCCESS;
		return $this;
	}


	public function warn($messages = [])
	{
		$this->status = self::WARNING;
		$this->message = implode(' ', $messages);
		return $this;
	}


	public function fail($message)
	{
		$this->status = self::FAIL;
		$this->message = $message;
		return $this;
	}


	public function __toString()
	{
		return (string) $this->number;
	}


	public function hasFailed()
	{
		return $this->status === self::FAIL;
	}


	public function hasWarnings()
	{
		return $this->status === self::WARNING;
	}


	public function setCommitMessage($commitMessage)
	{
		$this->commitMessage = $commitMessage;
		return $this;
	}


	public function getCommitMessage()
	{
		return $this->commitMessage;
	}

	public function getDeployOutput()
	{
		return $this->deployOutput;
	}


	public function setDeployOutput($deployOutput)
	{
		$this->deployOutput = $deployOutput;
		return $this;
	}


}


