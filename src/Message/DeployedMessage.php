<?php

declare(strict_types=1);

namespace Atoolo\Deployment\Message;

class DeployedMessage
{
    public function __construct(
        public readonly string $projectDir
    ) {
    }
}
