<?php

namespace Console\Command;


use Symfony\Component\Console;

/**
 * Create user command
 * @author Martin Bažík <martin@bazo.sk>
 */
class CreateUser extends Console\Command\Command
{

	/** @var \Security\UserManager */
	private $userManager;

	public function setUserManager(\Security\UserManager $userManager)
	{
		$this->userManager = $userManager;
	}


	protected function configure()
	{
		$this->setName('user:create')
				->setDescription('Creates a new user account');
	}


	protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
	{
		$output->writeln('<info>Creating new user account...</info>');

		$dialog = $this->getHelper('dialog');

		$loginValidator = function ($value) {
			if (trim($value) == '') {
				throw new \Exception('Login can not be empty');
			}

			return $value;
		};

		$passwordValidator = function ($value) {
			if (trim($value) == '') {
				throw new \Exception('The password can not be empty');
			}

			return $value;
		};

		$login		 = $dialog->askAndValidate($output, 'Please enter login: ', $loginValidator, $retries	 = 20);
		$password	 = $dialog->askHiddenResponseAndValidate($output, 'Please enter password: ', $passwordValidator, $retries	 = 20, true);

		$this->userManager->createUser($login, $password);

		$output->writeln(sprintf('<info>User %s successfully created.</info>', $login));
	}


}
