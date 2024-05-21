<?php

declare(strict_types=1);

namespace Atoolo\Deployment\Test\Service;

use Atoolo\Deployment\Service\StopWorkerOnRedeployListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Worker;

#[CoversClass(StopWorkerOnRedeployListener::class)]
class StopWorkerOnRedeployListenerTest extends TestCase
{
    private string $cacheDir = 'var/test/StopWorkerOnRedeployListener';
    private StopWorkerOnRedeployListener $listener;
    private LoggerInterface $logger;

    public function setUp(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        foreach (glob($this->cacheDir . '/worker_start_hash_*') as $file) {
            unlink($file);
        }
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->listener = new StopWorkerOnRedeployListener(
            $this->cacheDir,
            $this->logger
        );
    }

    public function testOnWorkerStarted(): void
    {
        $this->listener->onWorkerStarted();
        $count = count(glob($this->cacheDir . '/worker_start_hash_*'));
        $this->assertEquals(
            1,
            $count,
            'one worker start hash file should be created'
        );
    }

    public function testUnableToGetCacheDirRealPath(): void
    {
        $this->expectException(RuntimeException::class);
        new StopWorkerOnRedeployListener(
            '/abc',
            $this->logger
        );
    }

    public function testOnWorkerRunningIsNotIdle(): void
    {
        $worker = $this->createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, false);

        $worker->expects($this->never())
            ->method('stop');
        $this->listener->onWorkerRunning($event);
    }

    public function testOnWorkerRunningIsIdleShouldNotStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, true);

        $this->listener->onWorkerStarted();

        $worker->expects($this->never())
            ->method('stop');
        $this->listener->onWorkerRunning($event);
    }

    public function testOnWorkerRunningIsIdleShouldStop(): void
    {
        $worker = $this->createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, true);

        $this->listener->onWorkerStarted();

        $file = $this->cacheDir . '/worker_start_hash_' . getmypid();
        file_put_contents($file, 'other-hash');

        $worker->expects($this->once())
            ->method('stop');
        $this->listener->onWorkerRunning($event);
    }

    public function testOnWorkerRunningIsIdleShouldStopHashFileMissing(): void
    {
        $worker = $this->createMock(Worker::class);
        $event = new WorkerRunningEvent($worker, true);

        $worker->expects($this->once())
            ->method('stop');
        $this->listener->onWorkerRunning($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                WorkerStartedEvent::class => 'onWorkerStarted',
                WorkerRunningEvent::class => 'onWorkerRunning',
            ],
            StopWorkerOnRedeployListener::getSubscribedEvents(),
            'should return the correct events'
        );
    }
}
