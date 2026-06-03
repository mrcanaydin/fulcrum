<?php

declare(strict_types=1);

namespace Fulcrum\Storage\Adapters;

use Aws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;

class S3Adapter implements StorageAdapter
{
    /** @param array<string, mixed> $config */
    public function create(array $config): FilesystemAdapter
    {
        $bucket = $config['bucket'] ?? null;

        if (!is_string($bucket) || $bucket === '') {
            throw new InvalidArgumentException('S3 storage disk requires a non-empty [bucket] value.');
        }

        $clientConfig = [
            'version' => $config['version'] ?? 'latest',
            'region' => $config['region'] ?? 'us-east-1',
        ];

        if (isset($config['key'], $config['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ];
        }

        if (is_string($config['endpoint'] ?? null) && $config['endpoint'] !== '') {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        if (array_key_exists('use_path_style_endpoint', $config)) {
            $clientConfig['use_path_style_endpoint'] = (bool) $config['use_path_style_endpoint'];
        }

        $prefix = is_string($config['prefix'] ?? null) ? $config['prefix'] : '';

        return new AwsS3V3Adapter(new S3Client($clientConfig), $bucket, $prefix);
    }
}
