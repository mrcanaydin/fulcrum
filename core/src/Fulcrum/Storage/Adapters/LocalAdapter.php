<?php

declare(strict_types=1);

namespace Fulcrum\Storage\Adapters;

use InvalidArgumentException;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

class LocalAdapter implements StorageAdapter
{
    /** @param array<string, mixed> $config */
    public function create(array $config): FilesystemAdapter
    {
        $root = $config['root'] ?? null;

        if (!is_string($root) || $root === '') {
            throw new InvalidArgumentException('Local storage disk requires a non-empty [root] path.');
        }

        return new LocalFilesystemAdapter($root);
    }
}
