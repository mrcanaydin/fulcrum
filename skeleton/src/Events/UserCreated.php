<?php

declare(strict_types=1);

namespace App\Events;

final class UserCreated
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $locale = 'en',
    ) {}
}
