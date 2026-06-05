<?php

declare(strict_types=1);

namespace App\Jobs;

use Fulcrum\Mail\MailManager;
use Fulcrum\Mail\Message;
use Fulcrum\Queue\Job;
use Fulcrum\Internationalization\Translator;

class SendEmailVerificationJob implements Job
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
        private readonly string $locale = 'en',
    ) {}

    public function handle(MailManager $mail, Translator $translator): void
    {
        $mail->send(new Message(
            to: $this->email,
            subject: $translator->get('messages.verify_email_subject', locale: $this->locale, fallback: 'Verify your email address'),
            text: $translator->get(
                'messages.verify_email_text',
                ['token' => $this->token],
                $this->locale,
                'Use this verification token: :token',
            ),
            locale: $this->locale,
        ));
    }
}
