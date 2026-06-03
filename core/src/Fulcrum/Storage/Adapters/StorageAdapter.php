<?php

declare(strict_types=1);

namespace Fulcrum\Storage\Adapters;

use League\Flysystem\FilesystemAdapter;

interface StorageAdapter
{
    /** @param array<string, mixed> $config */
    public function create(array $config): FilesystemAdapter;
}
