<?php

declare(strict_types=1);

namespace Fulcrum\Foundation;

use Fulcrum\Container\Container;
use Fulcrum\Container\Contracts\ContainerInterface;
use Fulcrum\Container\ServiceProvider;
use Fulcrum\Foundation\Exceptions\Handler;
use Fulcrum\Logging\LoggerManager;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Router;
use Psr\Log\LoggerInterface;

/**
 * The Fulcrum application kernel.
 *
 * Orchestrates the full boot sequence:
 *   1. Create the DI container and register base bindings
 *   2. Load configuration (.env + config/ files)
 *   3. Register the global exception handler
 *   4. Discover and boot all service providers
 *   5. Dispatch the incoming HTTP request
 *
 * Usage (public/index.php):
 *   (new Application(dirname(__DIR__)))->boot()->run();
 */
class Application
{
    private Container $container;

    /** @var ServiceProvider[] */
    private array $providers = [];

    private bool $booted = false;

    public function __construct(private readonly string $basePath)
    {
        $this->container = new Container();
        $this->registerBaseBindings();
    }

    // ─── Boot ────────────────────────────────────────────────────────────────

    public function boot(): static
    {
        if ($this->booted) {
            return $this;
        }

        // Config must be available before any provider runs
        $config = new Config(
            $this->basePath('/config'),
            $this->basePath('/.env')
        );
        $this->container->instance(Config::class, $config);

        $loggerManager = new LoggerManager($config);
        $this->container->instance(LoggerManager::class, $loggerManager);
        $this->container->instance(LoggerInterface::class, $loggerManager->channel());

        // Register the exception handler early
        $handler = new Handler($config, $loggerManager->channel());
        $handler->register();
        $this->container->instance(Handler::class, $handler);

        // Discover → register → boot service providers
        $providerClasses = (new ModuleLoader($this->basePath, $config))->discover();
        $this->registerProviders($providerClasses);
        $this->bootProviders();

        $this->booted = true;

        return $this;
    }

    // ─── Run ─────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $request = Request::capture();
        $this->container->instance(Request::class, $request);

        $router = $this->container->make(Router::class);

        if (!$router instanceof Router) {
            throw new \RuntimeException('Router is not registered.');
        }

        $router->dispatch($request);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function container(): Container
    {
        return $this->container;
    }

    public function basePath(string $append = ''): string
    {
        return $this->basePath . ($append !== '' ? '/' . ltrim($append, '/') : '');
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function registerBaseBindings(): void
    {
        $this->container->instance(Application::class, $this);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(\Psr\Container\ContainerInterface::class, $this->container);
    }

    /** @param list<class-string> $classes */
    private function registerProviders(array $classes): void
    {
        foreach ($classes as $class) {
            /** @var ServiceProvider $provider */
            $provider = new $class($this->container);
            $provider->register();
            $this->providers[] = $provider;
        }
    }

    private function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }
}
