<?php

declare(strict_types=1);

namespace App\Jobs;

use Fulcrum\Mail\MailManager;
use Fulcrum\Mail\Message;
use Fulcrum\Queue\Job;

class SendEmailVerificationJob implements Job
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
    ) {}

    public function handle(MailManager $mail): void
    {
        $mail->send(new Message(
            to: $this->email,
            subject: 'Verify your email address',
            text: "Use this verification token: {$this->token}",
        ));
    }
}
