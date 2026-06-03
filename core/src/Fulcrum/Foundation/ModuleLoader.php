<?php

declare(strict_types=1);

namespace Fulcrum\Foundation;

/**
 * Discovers ServiceProvider class names from two sources:
 *
 *   1. config/app.php  → 'providers' array  (application-level)
 *   2. vendor packages that declare extra.fulcrum-providers in their
 *      composer.json  (third-party package auto-discovery)
 *
 * All discovered class names are merged and deduplicated before being
 * returned to Application::boot() for registration.
 */
class ModuleLoader
{
    public function __construct(
        private readonly string $basePath,
        private readonly Config $config,
    ) {}

    /**
     * @return list<class-string>
     */
    public function discover(): array
    {
        $providers = [];

        // 1. Application config
        $fromConfig = $this->config->get('app.providers', []);
        if (is_array($fromConfig)) {
            $providers = array_merge($providers, $fromConfig);
        }

        // 2. Vendor package auto-discovery
        $providers = array_merge($providers, $this->discoverFromVendor());

        // Deduplicate while preserving order
        return array_values(array_unique($providers));
    }

    /** @return list<class-string> */
    private function discoverFromVendor(): array
    {
        $installedJson = $this->basePath . '/vendor/composer/installed.json';

        if (!file_exists($installedJson)) {
            return [];
        }

        $installed = json_decode(file_get_contents($installedJson) ?: '{}', true);

        // Composer ≥ 2.0 wraps packages under a "packages" key
        $packages = is_array($installed['packages'] ?? null)
            ? $installed['packages']
            : (is_array($installed) ? $installed : []);

        $providers = [];

        foreach ($packages as $package) {
            $extra = $package['extra'] ?? [];
            $found = $extra['fulcrum-providers'] ?? [];

            if (is_array($found)) {
                $providers = array_merge($providers, $found);
            }
        }

        return $providers;
    }
}
