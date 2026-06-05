<?php

declare(strict_types=1);

namespace Fulcrum\Storage\GraphQL;

use Fulcrum\Auth\Attributes\RequiresAbility;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Authenticated;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\Storage\SignedUpload;
use Fulcrum\Storage\SignedUploadManager;

class SignedUploadMutation
{
    public function __construct(private readonly SignedUploadManager $uploads) {}

    /**
     * @param array<string, mixed> $args
     */
    #[Mutation(name: 'createSignedUpload', type: 'SignedUpload!', description: 'Create a direct S3-compatible PUT upload URL.')]
    #[Authenticated]
    #[RequiresAbility('uploads:create')]
    #[Arg(name: 'path', type: 'String!')]
    #[Arg(name: 'contentType', type: 'String!')]
    #[Arg(name: 'expiresIn', type: 'Int', defaultValue: 900)]
    #[Arg(name: 'disk', type: 'String')]
    public function createSignedUpload(mixed $root, array $args): SignedUpload
    {
        $path = $args['path'] ?? null;
        $contentType = $args['contentType'] ?? null;
        $expiresIn = $args['expiresIn'] ?? 900;
        $disk = $args['disk'] ?? null;

        if (!is_string($path) || !is_string($contentType) || $contentType === '') {
            throw new ClientException('Signed upload input is invalid.', 'SIGNED_UPLOAD_INPUT_INVALID');
        }
        if (!is_int($expiresIn) || ($disk !== null && !is_string($disk))) {
            throw new ClientException('Signed upload input is invalid.', 'SIGNED_UPLOAD_INPUT_INVALID');
        }

        return $this->uploads->create(
            $path,
            $contentType,
            $expiresIn,
            $disk,
        );
    }
}
