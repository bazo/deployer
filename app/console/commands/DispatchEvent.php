<?php

namespace Console\Command;

use Symfony\Component\Console;

/**
 * Delete cache
 * @author Martin Bažík <martin@bazo.sk>
 */
class DispatchEvent extends Console\Command\Command {

    protected function configure() {
        $this->setName('deploy:parseCommands')
             ->setDescription('Parses commands from command filr');
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {

    }
}

