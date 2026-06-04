<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Factories\UserFactory;
use Fulcrum\Database\Seeders\Seeder;

class DatabaseSeeder implements Seeder
{
    public function __construct(private readonly UserFactory $users) {}

    public function run(): void
    {
        $this->users->count(3)->create();
    }
}
