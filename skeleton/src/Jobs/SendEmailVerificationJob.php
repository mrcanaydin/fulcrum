<?php

declare(strict_types=1);

namespace App\Jobs;

use Fulcrum\Queue\Job;

class SendEmailVerificationJob implements Job
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {}

    public function handle(): void
    {
        $path = getcwd() . '/storage/logs/email-verifications.log';
        $line = json_encode([
            'message' => 'Send verification email.',
            'email' => $this->email,
            'token' => $this->token,
            'queued_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
    }
}
