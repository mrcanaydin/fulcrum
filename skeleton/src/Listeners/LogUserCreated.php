<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use Psr\Log\LoggerInterface;

class LogUserCreated
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function handle(UserCreated $event): void
    {
        $this->logger->info('User created', [
            'user_id' => $event->userId,
            'email' => $event->email,
        ]);
    }
}
