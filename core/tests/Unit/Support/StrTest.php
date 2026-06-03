<?php

declare(strict_types=1);

use Fulcrum\Support\Str;

describe('Str — case conversion', function () {
    it('converts to camelCase', function () {
        expect(Str::camel('hello_world'))->toBe('helloWorld')
            ->and(Str::camel('foo-bar-baz'))->toBe('fooBarBaz');
    });

    it('converts to PascalCase', function () {
        expect(Str::pascal('hello_world'))->toBe('HelloWorld');
    });

    it('converts to snake_case', function () {
        expect(Str::snake('helloWorld'))->toBe('hello_world')
            ->and(Str::snake('FooBarBaz'))->toBe('foo_bar_baz');
    });

    it('converts to kebab-case', function () {
        expect(Str::kebab('helloWorld'))->toBe('hello-world');
    });
});

describe('Str — inspection', function () {
    it('startsWith()', function () {
        expect(Str::startsWith('Fulcrum', 'Ful'))->toBeTrue()
            ->and(Str::startsWith('Fulcrum', 'xyz'))->toBeFalse();
    });

    it('endsWith()', function () {
        expect(Str::endsWith('Fulcrum', 'rum'))->toBeTrue()
            ->and(Str::endsWith('Fulcrum', 'xyz'))->toBeFalse();
    });

    it('contains()', function () {
        expect(Str::contains('Hello World', 'World'))->toBeTrue()
            ->and(Str::contains('Hello World', 'xyz'))->toBeFalse();
    });
});

describe('Str — manipulation', function () {
    it('limit() truncates long strings', function () {
        expect(Str::limit('Hello World', 5))->toBe('Hello...');
    });

    it('slug() generates a URL-safe string', function () {
        expect(Str::slug('Hello World!'))->toBe('hello-world');
    });

    it('random() returns a string of the requested length', function () {
        $rand = Str::random(32);
        expect(strlen($rand))->toBe(32);
    });

    it('uuid() returns a valid v4 UUID format', function () {
        $uuid = Str::uuid();
        expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });

    it('after() returns the portion after a substring', function () {
        expect(Str::after('foo@example.com', '@'))->toBe('example.com');
    });

    it('before() returns the portion before a substring', function () {
        expect(Str::before('foo@example.com', '@'))->toBe('foo');
    });
});
