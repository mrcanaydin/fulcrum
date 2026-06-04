<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

interface NotificationTransport
{
    public function send(Notification $notification): void;
}
