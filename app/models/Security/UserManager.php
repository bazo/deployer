<?php

namespace Security;

use Nette\Security\AuthenticationException;

/**
 * UserManager
 *
 * @author Martin Bažík <martin@bazo.sk>
 */
class UserManager extends \BaseManager implements \Nette\Security\IAuthenticator
{

	/**
	 * Authenticate user
	 * @param array $credentials
	 * @return type
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		list($login, $password) = $credentials;

		$user = $this->dm->getRepository('User')->findOneBy(['login' => $login]);

		if ($user === null or !password_verify($password, $user->getPassword())) {
			throw new AuthenticationException('Invalid credentials.', self::INVALID_CREDENTIAL);
		}

		return $user;
	}

	/**
	 * Create new user
	 * @param string $login
	 * @param string $password
	 */
	public function createUser($login, $password)
	{
		$hash = password_hash($password, PASSWORD_BCRYPT);
		$user = new \User($login, $hash);

		$this->dm->persist($user);
		$this->dm->flush();
	}

}
