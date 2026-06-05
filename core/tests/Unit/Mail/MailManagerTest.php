<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;
use Fulcrum\Mail\MailManager;
use Fulcrum\Mail\Message;

it('writes sent mail to the log transport', function () {
    $path = sys_get_temp_dir() . '/fulcrum-mail-' . bin2hex(random_bytes(6)) . '.log';
    $config = new Config(__DIR__ . '/missing');
    $config->set('mail.default', 'log');
    $config->set('mail.mailers.log', [
        'transport' => 'log',
        'path' => $path,
    ]);

    (new MailManager($config))->send(new Message(
        to: 'ada@example.com',
        subject: 'Hello',
        text: 'Welcome to Fulcrum',
        locale: 'en',
    ));

    $line = file_get_contents($path);
    expect($line)->toBeString()
        ->and($line)->toContain('ada@example.com')
        ->and($line)->toContain('Welcome to Fulcrum');
    expect($line)->toContain('"locale":"en"');

    unlink($path);
});
