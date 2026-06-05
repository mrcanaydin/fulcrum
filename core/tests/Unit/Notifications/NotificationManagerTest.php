<?php

declare(strict_types=1);

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;
use Fulcrum\Mail\MailManager;
use Fulcrum\Notifications\Notification;
use Fulcrum\Notifications\NotificationHookListener;
use Fulcrum\Notifications\NotificationManager;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Internationalization\Translator;

final class FulcrumNotificationTestEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
    ) {}
}

function notificationTestConfig(): Config
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $config->set('queue.default', 'sync');
    $config->set('queue.connections.sync', [
        'driver' => 'sync',
    ]);

    return $config;
}

it('writes push notifications to the log channel', function () {
    $path = sys_get_temp_dir() . '/fulcrum-notifications-' . bin2hex(random_bytes(6)) . '.log';
    $config = notificationTestConfig();
    $config->set('notifications.default', 'log');
    $config->set('notifications.channels.log', [
        'transport' => 'log',
        'path' => $path,
    ]);

    $manager = new NotificationManager(
        $config,
        new QueueManager($config, new DatabaseManager($config)),
    );

    $manager->sendNow(new Notification(
        to: 'user:123',
        title: 'Hello',
        body: 'You have a notification.',
        data: ['type' => 'test'],
        locale: 'en',
    ));

    $line = file_get_contents($path);
    expect($line)->toBeString()
        ->and($line)->toContain('user:123')
        ->and($line)->toContain('You have a notification.');
    expect($line)->toContain('"locale":"en"');

    unlink($path);
});

it('sends configured notification and mail hooks for events', function () {
    $notificationPath = sys_get_temp_dir() . '/fulcrum-hook-notifications-' . bin2hex(random_bytes(6)) . '.log';
    $mailPath = sys_get_temp_dir() . '/fulcrum-hook-mail-' . bin2hex(random_bytes(6)) . '.log';
    $config = notificationTestConfig();
    $config->set('notifications.default', 'log');
    $config->set('notifications.queue', false);
    $config->set('notifications.channels.log', [
        'transport' => 'log',
        'path' => $notificationPath,
    ]);
    $config->set('notifications.hooks', [
        FulcrumNotificationTestEvent::class => [
            [
                'enabled' => true,
                'queue' => false,
                'to' => 'user:{userId}',
                'title' => 'Welcome',
                'body' => 'Hello {email}',
                'data' => ['user_id' => '{userId}'],
            ],
        ],
    ]);
    $config->set('mail.default', 'log');
    $config->set('mail.mailers.log', [
        'transport' => 'log',
        'path' => $mailPath,
    ]);
    $config->set('notifications.mail_hooks', [
        FulcrumNotificationTestEvent::class => [
            [
                'enabled' => true,
                'queue' => false,
                'to' => '{email}',
                'subject' => 'Welcome {userId}',
                'text' => 'Thanks {email}',
            ],
        ],
    ]);

    $queues = new QueueManager($config, new DatabaseManager($config));
    $listener = new NotificationHookListener(
        $config,
        new NotificationManager($config, $queues),
        new MailManager($config),
        $queues,
        new Translator($config),
    );

    $listener->handle(new FulcrumNotificationTestEvent('123', 'ada@example.com'), FulcrumNotificationTestEvent::class);

    expect(file_get_contents($notificationPath))->toContain('Hello ada@example.com')
        ->and(file_get_contents($mailPath))->toContain('Welcome 123');

    unlink($notificationPath);
    unlink($mailPath);
});
