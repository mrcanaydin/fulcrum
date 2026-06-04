<?php

declare(strict_types=1);

namespace Fulcrum\Notifications;

use Fulcrum\Queue\Job;

class SendNotificationJob implements Job
{
    public function __construct(
        private readonly Notification $notification,
        private readonly ?string $channel = null,
    ) {}

    public function handle(NotificationManager $notifications): void
    {
        $notifications->sendNow($this->notification, $this->channel);
    }
}
