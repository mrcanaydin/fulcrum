<?php

declare(strict_types=1);

use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;
use Fulcrum\Foundation\Exceptions\Handler;
use Fulcrum\Logging\LoggerManager;
use Fulcrum\Logging\LoggingServiceProvider;
use Fulcrum\Logging\Loggers\FileLogger;
use Fulcrum\Logging\Loggers\NullLogger;
use Psr\Log\LoggerInterface;

it('writes structured file log records', function () {
    $root = sys_get_temp_dir() . '/fulcrum-logs-' . bin2hex(random_bytes(6));
    $path = $root . '/fulcrum.log';
    $logger = new FileLogger($path);

    $logger->info('Hello {name}', ['name' => 'Fulcrum']);

    $record = json_decode((string) file_get_contents($path), true);

    expect($record['level'])->toBe('info')
        ->and($record['message'])->toBe('Hello Fulcrum')
        ->and($record['context']['name'])->toBe('Fulcrum');

    unlink($path);
    rmdir($root);
});

it('resolves configured log channels', function () {
    $root = sys_get_temp_dir() . '/fulcrum-logs-' . bin2hex(random_bytes(6));
    $config = new Config(__DIR__ . '/missing');
    $config->set('logging.default', 'file');
    $config->set('logging.channels.file', [
        'driver' => 'file',
        'path' => $root . '/app.log',
    ]);

    $manager = new LoggerManager($config);

    expect($manager->channel())->toBeInstanceOf(FileLogger::class)
        ->and($manager->channel())->toBe($manager->channel());

    rmdir($root);
});

it('registers the logger manager and default logger', function () {
    $container = new Container();
    $config = new Config(__DIR__ . '/missing');
    $config->set('logging.default', 'null');
    $container->instance(Config::class, $config);

    (new LoggingServiceProvider($container))->register();

    expect($container->make(LoggerManager::class))->toBeInstanceOf(LoggerManager::class)
        ->and($container->make(LoggerInterface::class))->toBeInstanceOf(NullLogger::class);
});

it('reports exceptions through the configured logger', function () {
    $root = sys_get_temp_dir() . '/fulcrum-logs-' . bin2hex(random_bytes(6));
    $path = $root . '/exceptions.log';
    $config = new Config(__DIR__ . '/missing');
    $handler = new Handler($config, new FileLogger($path));

    $handler->report(new RuntimeException('Boom'));

    $record = json_decode((string) file_get_contents($path), true);

    expect($record['level'])->toBe('critical')
        ->and($record['message'])->toBe('Boom')
        ->and($record['context']['exception']['class'])->toBe(RuntimeException::class);

    unlink($path);
    rmdir($root);
});
