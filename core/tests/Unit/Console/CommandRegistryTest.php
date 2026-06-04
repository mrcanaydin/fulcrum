<?php

declare(strict_types=1);

use Fulcrum\Console\Command;
use Fulcrum\Console\CommandRegistry;
use Fulcrum\Container\Container;

class FulcrumTestCommand extends Command
{
    protected string $signature = 'demo:run';

    protected string $description = 'Run demo command.';

    public function handle(): int
    {
        $GLOBALS['fulcrum_command_option'] = $this->stringOption('sort', 'new');

        return self::SUCCESS;
    }
}

it('runs registered app commands with options', function () {
    $registry = new CommandRegistry(new Container(), [FulcrumTestCommand::class]);

    expect($registry->has('demo:run'))->toBeTrue()
        ->and($registry->run('demo:run', ['--sort=popular']))->toBe(Command::SUCCESS)
        ->and($GLOBALS['fulcrum_command_option'])->toBe('popular');
});
