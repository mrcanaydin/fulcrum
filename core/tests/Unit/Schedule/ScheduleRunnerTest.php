<?php

declare(strict_types=1);

use Fulcrum\Console\Command;
use Fulcrum\Console\ConsoleServiceProvider;
use Fulcrum\Database\DatabaseServiceProvider;
use Fulcrum\Foundation\Application;
use Fulcrum\Foundation\Config;
use Fulcrum\Schedule\Schedule;
use Fulcrum\Schedule\ScheduleRunner;

class FulcrumScheduledTestCommand extends Command
{
    protected string $signature = 'scheduled:test';

    public function handle(): int
    {
        $GLOBALS['fulcrum_scheduled_ran'] = true;

        return self::SUCCESS;
    }
}

it('runs due scheduled commands', function () {
    $app = new Application(__DIR__ . '/missing');
    $container = $app->container();
    $config = new Config(__DIR__ . '/missing');
    $config->set('console.commands', [FulcrumScheduledTestCommand::class]);
    $container->instance(Config::class, $config);
    (new ConsoleServiceProvider($container))->register();
    $GLOBALS['fulcrum_scheduled_ran'] = false;

    $ran = (new ScheduleRunner($app))->run([
        Schedule::command('scheduled:test')->everyFiveMinutes(),
    ], now: 300);

    expect($ran)->toBe(['scheduled:test'])
        ->and($GLOBALS['fulcrum_scheduled_ran'])->toBeTrue();
});
