<?php

declare(strict_types=1);

namespace Fulcrum\Storage;

use Fulcrum\Foundation\Config;
use Fulcrum\Storage\Adapters\LocalAdapter;
use Fulcrum\Storage\Adapters\S3Adapter;
use Fulcrum\Storage\Adapters\StorageAdapter;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;

class StorageManager
{
    /** @var array<string, FilesystemOperator> */
    private array $disks = [];

    /** @var array<string, StorageAdapter> */
    private array $adapters;

    public function __construct(private readonly Config $config)
    {
        $this->adapters = [
            'local' => new LocalAdapter(),
            's3' => new S3Adapter(),
        ];
    }

    public function disk(?string $name = null): FilesystemOperator
    {
        $name ??= $this->getDefaultDisk();

        if (!isset($this->disks[$name])) {
            $this->disks[$name] = $this->makeDisk($name);
        }

        return $this->disks[$name];
    }

    public function getDefaultDisk(): string
    {
        $default = $this->config->get('storage.default', 'local');

        return is_string($default) && $default !== '' ? $default : 'local';
    }

    public function extend(string $driver, StorageAdapter $adapter): void
    {
        $this->adapters[$driver] = $adapter;
    }

    private function makeDisk(string $name): FilesystemOperator
    {
        $config = $this->config->get("storage.disks.{$name}");

        if (!is_array($config)) {
            throw new InvalidArgumentException("Storage disk [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? null;

        if (!is_string($driver) || $driver === '') {
            throw new InvalidArgumentException("Storage disk [{$name}] requires a driver.");
        }

        if (!isset($this->adapters[$driver])) {
            throw new InvalidArgumentException("Unsupported storage driver [{$driver}].");
        }

        return new Filesystem($this->adapters[$driver]->create($config));
    }
}
