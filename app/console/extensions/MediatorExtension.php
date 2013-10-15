<?php

namespace Extensions;

use Nette\Framework;

/**
 * Console service.
 *
 * @author bazo
 */
class MediatorExtension extends \Nette\DI\CompilerExtension
{

	public function loadConfiguration()
	{
		$containerBuilder = $this->getContainerBuilder();

		$containerBuilder
				->addDefinition($this->prefix('eventDispatcher'))
				->addTag('mediator')
				->setClass('Symfony\Component\EventDispatcher\EventDispatcher');

		$containerBuilder
				->addDefinition('mediator')
				->setClass('Symfony\Component\EventDispatcher\EventDispatcher')
				->setFactory('@container::getService', array($this->prefix('eventDispatcher')))
				->setAutowired(FALSE);
	}


	public function afterCompile(\Nette\PhpGenerator\ClassType $class)
	{

		$container = $this->getContainerBuilder();
		$initialize = $class->methods['initialize'];

		$mediators = $container->findByTag('mediator');
		$subscribers = $container->findByTag('subscriber');

		foreach ($mediators as $mediatorName => $mediator) {

			foreach ($subscribers as $subscriberName => $subscriber) {
				$initialize->addBody('$this->getService(?)->addSubscriber($this->getService(?));', [$mediatorName, $subscriberName]);
			}
		}
	}


}

