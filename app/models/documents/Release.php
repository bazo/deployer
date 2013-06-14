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
	 * @var \DateTime
	 */
	private $date;
	
	/**
	 * @ODM\Int
	 * @var int
	 */
	private $number;
	
	/**
	 * @ODM\ReferenceOne(targetDocument="Application")
	 * @var \Application
	 */
	private $application;
	
	/**
	 * @ODM\String
	 * @var string
	 */
	private $status;
	
	/**
	 * @ODM\String
	 * @var string
	 */
	private $branch;
	
	/**
	 * @ODM\String
	 * @var string
	 */
	private $commit;
	
	/**
	 * @ODM\String
	 * @var string
	 */
	private $message;
	
	public function __construct(\Application $application, $branch)
	{
		$this->application = $application;
		$this->branch = $branch;
		
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


	public function getFailReason()
	{
		return $this->failReason;
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
		return (string)$this->number;
	}
	
	




}
