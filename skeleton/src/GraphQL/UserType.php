<?php

declare(strict_types=1);

namespace App\GraphQL;

use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'User', description: 'Example API user.')]
class UserType
{
    #[Field(type: 'ID!')]
    public string $id;

    #[Field(type: 'String!')]
    public string $name;

    #[Field(type: 'String!')]
    public string $email;

    #[Field(type: 'String')]
    public ?string $avatar = null;

    #[Field(type: 'String')]
    public ?string $gender = null;

    #[Field(type: 'String')]
    public ?string $birthday = null;

    #[Field(type: 'String')]
    public ?string $email_verified_at = null;

    #[Field(type: 'String')]
    public ?string $banned_at = null;

    #[Field(type: 'String')]
    public ?string $ban_reason = null;

    #[Field(type: 'String')]
    public ?string $created_at = null;

    #[Field(type: 'String')]
    public ?string $updated_at = null;
}
