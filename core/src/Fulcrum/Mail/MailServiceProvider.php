<?php

declare(strict_types=1);

namespace Fulcrum\Mail;

use Fulcrum\Container\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(MailManager::class, MailManager::class);
    }
}
