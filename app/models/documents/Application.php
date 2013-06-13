<?php

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Nette\Utils\Strings;

/**
 * Application
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @ODM\Document
 */
class Application
{

	/**
	 * @var string
	 * @ODM\Id
	 */
	private $id;

	/**
	 * @var string
	 * @ODM\String
	 * @ODM\Index(unique=true)
	 */
	private $name;
	
	/**
	 * @var string
	 * @ODM\String
	 * @ODM\Index(unique=true)
	 */
	private $repoName;

	/**
	 * @var array 
	 * @ODM\Hash
	 */
	private $settings;
	
	/**
	 * @param string $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
		$this->repoName = Strings::webalize($name);
	}

	public function getId()
	{
		return $this->id;
	}

	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return array
	 */
	public function getSettings()
	{
		if($this->settings === NULL) {
			return [];
		}
		return $this->settings;
	}


	/**
	 * @param array $settings
	 * @return Application
	 */
	public function setSettings(array $settings)
	{
		$this->settings = $settings;
		return $this;
	}
	
	public function getRepoName()
	{
		return $this->repoName;
	}



}