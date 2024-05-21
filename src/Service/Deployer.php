<?php

declare(strict_types=1);

namespace Atoolo\Deployment\Service;

use Psr\Log\LoggerAwareTrait;
use SP\Sitepark\RoutingBundle\Service\Task\Executable;

class Deployer
{
    use LoggerAwareTrait;

    /**
     * @var iterable|DeploymentExecutable[]
     */
    private $deploymentTasks;

    /**
     * @param iterable|DeploymentExecutable[] $depoymentTasks
     */
    public function __construct(iterable $deploymentTasks)
    {
        $this->deploymentTasks = $deploymentTasks;
    }

    public function execute(): bool
    {

        $successful = true;
        foreach ($this->deploymentTasks as $task) {
            try {
                $task->execute();
            } catch (\Throwable $throwable) {
                $successful = false;
                $this->logger->warning(
                    'An exception occurred during deployment task: ' .
                        $throwable->getMessage(),
                    ['exception' => $throwable, 'task' => get_class($task)]
                );
            }
        }

        return $successful;
    }
}
