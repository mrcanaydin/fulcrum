<?php

declare(strict_types=1);

namespace Fulcrum\Storage;

use Aws\S3\S3Client;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ClientException;

class SignedUploadManager
{
    public function __construct(private readonly Config $config) {}

    public function create(string $path, string $contentType, int $expiresIn = 900, ?string $disk = null): SignedUpload
    {
        $disk ??= $this->string('storage.default', 'local');
        $settings = $this->config->get("storage.disks.{$disk}");

        if (!is_array($settings) || ($settings['driver'] ?? null) !== 's3') {
            throw new ClientException('Signed uploads require an S3-compatible storage disk.', 'SIGNED_UPLOAD_UNSUPPORTED');
        }

        $path = ltrim(trim($path), '/');
        if ($path === '' || str_contains($path, '..') || !preg_match('/^[A-Za-z0-9._\/-]+$/', $path)) {
            throw new ClientException('Upload path is invalid.', 'SIGNED_UPLOAD_PATH_INVALID');
        }

        $contentType = strtolower(trim($contentType));
        if ($contentType === '' || !preg_match('/^[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*$/', $contentType)) {
            throw new ClientException('Upload content type is invalid.', 'SIGNED_UPLOAD_CONTENT_TYPE_INVALID');
        }

        $allowedContentTypes = $this->stringList($this->config->get('storage.signed_uploads.allowed_content_types', []));
        if ($allowedContentTypes !== [] && !in_array($contentType, $allowedContentTypes, true)) {
            throw new ClientException('Upload content type is not allowed.', 'SIGNED_UPLOAD_CONTENT_TYPE_NOT_ALLOWED');
        }

        $bucket = $settings['bucket'] ?? null;
        if (!is_string($bucket) || $bucket === '') {
            throw new ClientException('Signed upload storage is not configured.', 'SIGNED_UPLOAD_NOT_CONFIGURED');
        }

        $minTtl = $this->int('storage.signed_uploads.min_ttl_seconds', 60);
        $maxTtl = max($minTtl, $this->int('storage.signed_uploads.max_ttl_seconds', 3600));
        $expiresIn = max($minTtl, min($expiresIn, $maxTtl));
        $prefix = is_string($settings['prefix'] ?? null) ? trim($settings['prefix'], '/') : '';
        $key = $prefix !== '' ? $prefix . '/' . $path : $path;
        $client = new S3Client($this->clientConfig($settings));
        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);
        $request = $client->createPresignedRequest($command, "+{$expiresIn} seconds");

        return new SignedUpload(
            (string) $request->getUri(),
            'PUT',
            ['Content-Type' => $contentType],
            $path,
            time() + $expiresIn,
        );
    }

    /** @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function clientConfig(array $settings): array
    {
        $config = [
            'version' => $settings['version'] ?? 'latest',
            'region' => $settings['region'] ?? 'us-east-1',
        ];

        if (is_string($settings['key'] ?? null) && is_string($settings['secret'] ?? null)) {
            $config['credentials'] = ['key' => $settings['key'], 'secret' => $settings['secret']];
        }
        if (is_string($settings['endpoint'] ?? null) && $settings['endpoint'] !== '') {
            $config['endpoint'] = $settings['endpoint'];
        }
        if (array_key_exists('use_path_style_endpoint', $settings)) {
            $config['use_path_style_endpoint'] = (bool) $settings['use_path_style_endpoint'];
        }

        return $config;
    }

    private function string(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        return is_int($value) || is_string($value) || is_float($value)
            ? max(1, (int) $value)
            : $default;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? strtolower(trim($item)) : '', $value),
            static fn (string $item): bool => $item !== ''
        ));
    }
}
