<?php

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Description of User
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @ODM\Document
 */
class User implements Nette\Security\IIdentity
{

	/**
	 * @var string
	 * @ODM\Id
	 */
	private $id;

	/**
	 * @var string
	 * @ODM\Field(type="string")
	 * @ODM\Index(unique=true)
	 */
	private $login;

	/**
	 * @var string
	 * @ODM\Field(type="string")
	 */
	private $password;

	/**
	 * @param string $login
	 * @param string $password
	 */
	public function __construct($login, $password)
	{
		$this->login = $login;
		$this->password = $password;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getLogin()
	{
		return $this->login;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getRoles()
	{
		return [
			'user'
		];
	}

}
