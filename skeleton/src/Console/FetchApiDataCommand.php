<?php

declare(strict_types=1);

namespace App\Console;

use App\Jobs\FetchApiDataJob;
use Fulcrum\Console\Command;
use Fulcrum\Queue\QueueManager;

class FetchApiDataCommand extends Command
{
    protected string $signature = 'api-data:fetch';

    protected string $description = 'Queue an API data import job.';

    public function __construct(private readonly QueueManager $queues) {}

    public function handle(): int
    {
        $sort = $this->stringOption('sort', 'new');

        $this->queues->dispatch(new FetchApiDataJob($sort));
        $this->line("Queued API data import with sort={$sort}.");

        return self::SUCCESS;
    }
}
