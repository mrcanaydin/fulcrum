<?php

declare(strict_types=1);

namespace Fulcrum\Validation;

use Fulcrum\Container\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Sanitizer::class, Sanitizer::class);
        $this->container->singleton(Validator::class, Validator::class);
    }
}
