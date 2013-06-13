<?php

namespace Extensions;

use Nette\Framework;

/**
 * Console service.
 *
 * @author bazo
 */
class ConsoleExtension extends \Nette\DI\CompilerExtension
{

	/**
     * Processes configuration data
     *
     * @return void
     */
    public function loadConfiguration() {
        $container = $this->getContainerBuilder();

        // console application
        $container->addDefinition($this->prefix('console'))
                  ->setClass('Symfony\Component\Console\Application')
                  ->setFactory('Extensions\ConsoleExtension::createConsole', array('@container'))
                  ->setAutowired(false);

        // aliases
        $container->addDefinition('console')
                  ->setClass('Symfony\Component\Console\Application')
                  ->setFactory('@container::getService', array($this->prefix('console')));

		
		$this->compiler->parseServices($container, $this->loadFromFile($container->expand('%appDir%') . '/config/console.neon'), 'console');
	}

    /**
     * @param \Nette\DI\Container
     * @param \Symfony\Component\Console\Helper\HelperSet
     * @return \Symfony\Component\Console\Application
     */
    public static function createConsole(\Nette\DI\Container $container, \Symfony\Component\Console\Helper\HelperSet $helperSet = null) {
        $console = new \Symfony\Component\Console\Application(Framework::NAME . " Command Line Interface", Framework::VERSION);

        if (!$helperSet) {
            $helperSet = new \Symfony\Component\Console\Helper\HelperSet;

            foreach (array_keys($container->findByTag('consoleHelper')) as $helperName) {
				$helper = $container->getService($helperName);

				$helperName = substr($helperName, $start = 8); //console. - 8 cahrs

				$helperSet->set($helper, $helperName);
			}
        }

        $console->setHelperSet($helperSet);
        $console->setCatchExceptions(false);

        $commands = array();
        foreach (array_keys($container->findByTag('consoleCommand')) as $name) {
            $commands[] = $container->getService($name);
        }
        $console->addCommands($commands);

        return $console;
    }
}

