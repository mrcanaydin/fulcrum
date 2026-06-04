<?php

declare(strict_types=1);

namespace Fulcrum\Mail;

interface MailTransport
{
    public function send(Message $message): void;
}
