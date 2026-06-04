<?php

declare(strict_types=1);

namespace App\Jobs;

use Fulcrum\Queue\Job;

class FetchApiDataJob implements Job
{
    public function __construct(private readonly string $sort = 'new') {}

    public function handle(): void
    {
        $path = getcwd() . '/storage/logs/api-data-imports.log';
        $line = json_encode([
            'message' => 'Fetched API data.',
            'sort' => $this->sort,
            'processed_at' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
    }
}
