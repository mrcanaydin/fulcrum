<?php

declare(strict_types=1);

use Fulcrum\Validation\ValidationException;
use Fulcrum\Validation\Validator;

it('sanitizes explicitly configured fields before validation', function () {
    $data = (new Validator())->validate(
        ['email' => ' USER@Example.COM ', 'name' => ' <b>Alice</b> '],
        ['email' => 'required|email', 'name' => 'required|string|min:3'],
        ['email' => ['email', 'lower'], 'name' => ['trim', 'strip_tags']]
    );

    expect($data['email'])->toBe('user@example.com')
        ->and($data['name'])->toBe('Alice');
});

it('throws api-safe validation exceptions', function () {
    try {
        (new Validator())->validate(
            ['email' => 'nope', 'role' => 'owner'],
            ['email' => 'required|email', 'role' => 'required|in:admin,user']
        );
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['email', 'role'])
            ->and($e->toGraphQLError()['extensions']['code'])->toBe('VALIDATION_FAILED')
            ->and($e->toGraphQLError()['extensions']['validation'])->toHaveKeys(['email', 'role']);
        return;
    }

    throw new RuntimeException('Validation exception was not thrown.');
});

it('coerces scalar values through explicit sanitizers', function () {
    $data = (new Validator())->validate(
        ['age' => '42', 'active' => 'true'],
        ['age' => 'required|integer|min:18', 'active' => 'required|boolean'],
        ['age' => ['int'], 'active' => ['bool']]
    );

    expect($data['age'])->toBe(42)
        ->and($data['active'])->toBeTrue();
});

it('validates strict date formats', function () {
    $validator = new Validator();
    $data = $validator->validate(
        ['birthday' => '2000-02-29', 'optional_date' => null],
        ['birthday' => 'required|date_format:Y-m-d', 'optional_date' => 'nullable|date_format:Y-m-d']
    );

    expect($data['birthday'])->toBe('2000-02-29')
        ->and($data['optional_date'])->toBeNull();

    foreach (['2026-02-31', '31-12-2026', '2026-2-3'] as $birthday) {
        expect(fn () => $validator->validate(
            ['birthday' => $birthday],
            ['birthday' => 'required|date_format:Y-m-d']
        ))->toThrow(ValidationException::class);
    }
});
