<?php

declare(strict_types=1);

namespace Atoolo\Deployment\Console\Command;

use Atoolo\Deployment\Service\Deployer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'deployment:deploy')]
final class DeployCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     */
    public function __construct(
        private readonly Deployer $deployer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Executes all Deployer-Task to deploy ' .
            'the application to be production ready'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $successful = $this->deployer->execute();
        return $successful ? Command::SUCCESS : Command::FAILURE;
    }
}
