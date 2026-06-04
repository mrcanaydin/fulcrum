<?php

declare(strict_types=1);

namespace Fulcrum\Mail;

use Fulcrum\Queue\Job;

class SendMailJob implements Job
{
    public function __construct(
        private readonly Message $message,
        private readonly ?string $mailer = null,
    ) {}

    public function handle(MailManager $mail): void
    {
        $mail->send($this->message, $this->mailer);
    }
}
