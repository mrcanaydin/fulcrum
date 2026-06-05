<?php

declare(strict_types=1);

namespace Fulcrum\Auth;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Auth\Models\PersonalAccessToken;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PersonalAccessToken::class, PersonalAccessToken::class);
        $this->container->singleton(TokenManager::class, TokenManager::class);
        $this->container->singleton(TokenAuthenticator::class, TokenAuthenticator::class);
        $this->container->singleton(PermissionManager::class, PermissionManager::class);
    }

    public function boot(): void
    {
        // Setup logic if any
    }
}
