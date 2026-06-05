<?php

declare(strict_types=1);

namespace Fulcrum\Internationalization;

use Fulcrum\Foundation\Config;
use Fulcrum\Routing\Request;

class LocaleResolver
{
    public function __construct(private readonly Config $config) {}

    /** @param array<string, mixed>|null $user */
    public function resolve(Request $request, ?array $user = null): string
    {
        $candidates = [
            $request->explicitLocale(),
            is_string($user['locale'] ?? null) ? $user['locale'] : null,
            ...$request->acceptedLocales(),
            $this->defaultLocale(),
        ];

        foreach ($candidates as $candidate) {
            $locale = $this->supportedLocale($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $this->defaultLocale();
    }

    public function defaultLocale(): string
    {
        $locale = $this->config->get('app.locale', 'en');

        return is_string($locale) && $locale !== '' ? $this->normalize($locale) : 'en';
    }

    private function supportedLocale(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $locale = $this->normalize($locale);
        $supported = $this->supportedLocales();

        if (in_array($locale, $supported, true)) {
            return $locale;
        }

        $language = explode('-', $locale, 2)[0];

        return in_array($language, $supported, true) ? $language : null;
    }

    /** @return list<string> */
    private function supportedLocales(): array
    {
        $configured = $this->config->get('app.supported_locales', [$this->defaultLocale()]);

        if (!is_array($configured)) {
            return [$this->defaultLocale()];
        }

        return array_values(array_unique(array_map(
            fn (string $locale): string => $this->normalize($locale),
            array_filter($configured, is_string(...)),
        )));
    }

    private function normalize(string $locale): string
    {
        return str_replace('_', '-', strtolower(trim($locale)));
    }
}
