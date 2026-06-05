<?php

declare(strict_types=1);

namespace Fulcrum\Internationalization;

use Fulcrum\Container\ServiceProvider;

class InternationalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(LocaleResolver::class, LocaleResolver::class);
        $this->container->singleton(Translator::class, Translator::class);
    }
}
