<?php

declare(strict_types=1);

namespace Fulcrum\Internationalization;

use Fulcrum\Foundation\Config;

class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $catalogs = [];

    private string $locale;

    public function __construct(private readonly Config $config)
    {
        $this->locale = $this->defaultLocale();
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->normalize($locale);
    }

    /** @param array<string, scalar|null> $replace */
    public function get(string $key, array $replace = [], ?string $locale = null, ?string $fallback = null): string
    {
        $locale = $this->normalize($locale ?? $this->locale);
        $value = $this->catalogValue($locale, $key)
            ?? $this->catalogValue($this->fallbackLocale(), $key)
            ?? $fallback
            ?? $key;

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    public function cacheKey(string $key, ?string $locale = null): string
    {
        return $this->normalize($locale ?? $this->locale) . ':' . $key;
    }

    private function catalogValue(string $locale, string $key): ?string
    {
        [$group, $item] = array_pad(explode('.', $key, 2), 2, '');

        if ($group === '' || $item === '') {
            return null;
        }

        $catalog = $this->catalog($locale, $group);
        $value = $catalog;

        foreach (explode('.', $item) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }

            $value = $value[$part];
        }

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private function catalog(string $locale, string $group): array
    {
        $key = $locale . ':' . $group;

        if (array_key_exists($key, $this->catalogs)) {
            return $this->catalogs[$key];
        }

        $path = $this->path() . '/' . $locale . '/' . $group . '.php';
        $catalog = is_file($path) ? require $path : [];

        return $this->catalogs[$key] = is_array($catalog) ? $catalog : [];
    }

    private function path(): string
    {
        $path = $this->config->get('app.lang_path', getcwd() . '/lang');

        return is_string($path) && $path !== '' ? rtrim($path, '/') : getcwd() . '/lang';
    }

    private function defaultLocale(): string
    {
        $locale = $this->config->get('app.locale', 'en');

        return is_string($locale) && $locale !== '' ? $this->normalize($locale) : 'en';
    }

    private function fallbackLocale(): string
    {
        $locale = $this->config->get('app.fallback_locale', $this->defaultLocale());

        return is_string($locale) && $locale !== '' ? $this->normalize($locale) : $this->defaultLocale();
    }

    private function normalize(string $locale): string
    {
        return str_replace('_', '-', strtolower(trim($locale)));
    }
}
