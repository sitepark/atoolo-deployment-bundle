<?php

declare(strict_types=1);

namespace Atoolo\Deployment\Service;

use Atoolo\Deployment\Message\DeployedMessage;
use Atoolo\Deployment\Message\UndeployedMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * If the application is redeployed, this is done by setting a symlink to
 * the newly installed version. The old project directory is still retained
 * to enable a rollback. After redeploying the application, all running
 * Massenger workers are invalid and must be stopped.
 *
 * To recognize this, a file is stored in the cache directory of the project
 * in which a random hash value is saved. The `WorkerRunningEvent` is used to
 * check whether the file still exists in the project cache directory and
 * whether it contains the expected hash value. If this is not the case, the
 * project directory has changed and the worker is stopped.
 *
 * The worker is restarted via a process manager such as
 * [Supervisor](http://supervisord.org/), which monitors the process and also
 * restarts it if the process has been stopped. The worker process is
 * then restarted for the newly deployed project.
 */
class StopWorkerOnRedeployListener implements EventSubscriberInterface
{
    private string $projectDir;
    private string $workerStartHashFile;
    private ?string $workerStartHash = null;

    public function __construct(
        string $projectDir,
        string $cacheDir,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
        $dir = realpath($cacheDir);
        if ($dir === false) {
            throw new RuntimeException(
                'Could not create worker start hash file in ' . $cacheDir
            );
        }
        $workerStartHashFile = realpath($dir) .
            '/worker_start_hash_' .
            getmypid();
        $this->workerStartHashFile = $workerStartHashFile;
        $this->projectDir = realpath($projectDir);
    }

    public function onWorkerStarted(): void
    {
        $this->workerStartHash = bin2hex(random_bytes(18));
        $this->logger->info(
            "start redeploy listener with " .
            $this->workerStartHashFile . ' ' .
            'and hash ' . $this->workerStartHash
        );
        file_put_contents($this->workerStartHashFile, $this->workerStartHash);
        $this->bus->dispatch(new DeployedMessage($this->projectDir));
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            return;
        }
        if (!$this->shouldStop()) {
            return;
        }

        $this->logger->info(
            'The project directory has changed. This project was undeployed.'
        );
        $this->bus->dispatch(new UndeployedMessage($this->projectDir));
        $this->logger->info(
            'The worker is stopped.'
        );
        $event->getWorker()->stop();
    }

    private function shouldStop(): bool
    {
        if (!is_file($this->workerStartHashFile)) {
            return true;
        }

        $workerStartHash = file_get_contents($this->workerStartHashFile);
        return $workerStartHash !== $this->workerStartHash;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
