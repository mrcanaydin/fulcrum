<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;
use Fulcrum\Internationalization\LocaleResolver;
use Fulcrum\Internationalization\Translator;
use Fulcrum\Routing\Request;
use Fulcrum\Validation\ValidationException;
use Fulcrum\Validation\Validator;

function i18nTestConfig(): Config
{
    $path = sys_get_temp_dir() . '/fulcrum-i18n-' . bin2hex(random_bytes(6));
    mkdir($path . '/en', 0777, true);
    mkdir($path . '/tr', 0777, true);
    file_put_contents($path . '/en/messages.php', "<?php return ['hello' => 'Hello :name'];");
    file_put_contents($path . '/tr/messages.php', "<?php return ['hello' => 'Merhaba :name'];");
    file_put_contents($path . '/tr/validation.php', "<?php return ['failed' => 'Geçersiz veri.', 'required' => ':field zorunludur.'];");

    $config = new Config(__DIR__ . '/missing');
    $config->set('app.locale', 'en');
    $config->set('app.fallback_locale', 'en');
    $config->set('app.supported_locales', ['en', 'tr']);
    $config->set('app.lang_path', $path);

    return $config;
}

it('resolves locale by explicit input user preference accept-language and default', function () {
    $resolver = new LocaleResolver(i18nTestConfig());

    expect($resolver->resolve(new Request('POST', '/graphql', ['HTTP_ACCEPT_LANGUAGE' => 'tr;q=0.9,en;q=0.8'], ['locale' => 'en']), ['locale' => 'tr']))->toBe('en')
        ->and($resolver->resolve(new Request('POST', '/graphql', ['HTTP_ACCEPT_LANGUAGE' => 'en'], []), ['locale' => 'tr']))->toBe('tr')
        ->and($resolver->resolve(new Request('POST', '/graphql', ['HTTP_ACCEPT_LANGUAGE' => 'tr-TR,tr;q=0.8'], [])))->toBe('tr')
        ->and($resolver->resolve(new Request('POST', '/graphql', [], [])))->toBe('en');
});

it('translates messages with locale fallback and locale-aware cache keys', function () {
    $translator = new Translator(i18nTestConfig());

    expect($translator->get('messages.hello', ['name' => 'Ada'], 'tr'))->toBe('Merhaba Ada')
        ->and($translator->get('messages.hello', ['name' => 'Ada'], 'de'))->toBe('Hello Ada')
        ->and($translator->cacheKey('profile', 'tr'))->toBe('tr:profile');
});

it('localizes validation messages without changing the stable error code', function () {
    $translator = new Translator(i18nTestConfig());
    $translator->setLocale('tr');
    $validator = new Validator(translator: $translator);

    try {
        $validator->validate([], ['email' => 'required']);
    } catch (ValidationException $exception) {
        expect($exception->getMessage())->toBe('Geçersiz veri.')
            ->and($exception->errors()['email'][0])->toBe('email zorunludur.')
            ->and($exception->getExtensions()['code'])->toBe('VALIDATION_FAILED');
        return;
    }

    throw new RuntimeException('Validation exception was not thrown.');
});
