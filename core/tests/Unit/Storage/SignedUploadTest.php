<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\Storage\SignedUploadManager;

it('creates direct S3 signed upload instructions', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'uploads',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.signed_uploads.allowed_content_types', ['image/png']);
    $config->set('storage.signed_uploads.allowed_disks', ['s3']);

    $upload = (new SignedUploadManager($config))->create('users/1/avatar.png', 'image/png', 300);

    expect($upload->method)->toBe('PUT')
        ->and($upload->url)->toContain('X-Amz-Signature')
        ->and($upload->headers['Content-Type'])->toBe('image/png');
});

it('rejects unsupported signed upload disks and unsafe paths', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 'local');
    $config->set('storage.disks.local', ['driver' => 'local', 'root' => '/tmp']);

    expect(fn () => (new SignedUploadManager($config))->create('file.txt', 'text/plain'))
        ->toThrow(ClientException::class);

    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', ['driver' => 's3', 'bucket' => 'uploads']);
    expect(fn () => (new SignedUploadManager($config))->create('../secret', 'text/plain'))
        ->toThrow(ClientException::class);
});

it('rejects disallowed signed upload content types', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', ['driver' => 's3', 'bucket' => 'uploads']);
    $config->set('storage.signed_uploads.allowed_disks', ['s3']);
    $config->set('storage.signed_uploads.allowed_content_types', ['image/png']);

    expect(fn () => (new SignedUploadManager($config))->create('users/1/avatar.svg', 'image/svg+xml'))
        ->toThrow(ClientException::class, 'Upload content type is not allowed.');
});

it('rejects invalid signed upload content types and clamps ttl bounds', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'uploads',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.signed_uploads.allowed_disks', ['s3']);
    $config->set('storage.signed_uploads.max_ttl_seconds', 120);

    expect(fn () => (new SignedUploadManager($config))->create('users/1/avatar.png', 'not a mime'))
        ->toThrow(ClientException::class, 'Upload content type is invalid.');

    $upload = (new SignedUploadManager($config))->create('users/1/avatar.png', 'IMAGE/PNG', 600);

    expect($upload->headers['Content-Type'])->toBe('image/png')
        ->and($upload->expiresAt)->toBeLessThanOrEqual(time() + 120);
});

it('defaults signed uploads to the configured storage disk and rejects unlisted disks', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'uploads',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.disks.private', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'private',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.signed_uploads.allowed_content_types', ['image/png']);

    $manager = new SignedUploadManager($config);

    expect($manager->create('users/1/avatar.png', 'image/png')->url)->toContain('/uploads/')
        ->and(fn () => $manager->create('users/1/avatar.png', 'image/png', 300, 'private'))
        ->toThrow(ClientException::class, 'Signed upload disk is not allowed.');
});

it('allows explicitly whitelisted signed upload disks', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('storage.default', 's3');
    $config->set('storage.disks.s3', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'uploads',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.disks.private', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'private',
        'endpoint' => 'https://storage.example.com',
        'use_path_style_endpoint' => true,
    ]);
    $config->set('storage.signed_uploads.allowed_disks', ['s3', 'private']);
    $config->set('storage.signed_uploads.allowed_content_types', ['image/png']);

    $upload = (new SignedUploadManager($config))->create('users/1/avatar.png', 'image/png', 300, 'private');

    expect($upload->url)->toContain('/private/');
});
