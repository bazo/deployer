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
	 * @ODM\ReferenceOne(targetDocument="Release")
	 * @var Release
	 */
	private $currentRelease;
	
	/**
	 * @param string $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
		$this->repoName = Strings::webalize($name);
		$this->settings = [];
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

	public function getSetting($setting)
	{
		$settings = $this->getSettings();
		return isset($settings[$setting]) ? $settings[$setting] : NULL;
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

	
	public function setCurrentRelease(Release $currentRelease)
	{
		$this->currentRelease = $currentRelease;
		return $this;
	}
	
	public function getCurrentRelease()
	{
		return $this->currentRelease;
	}

}
