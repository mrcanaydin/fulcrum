<?php

declare(strict_types=1);

use Fulcrum\Support\Arr;

describe('Arr — dot notation get/set', function () {
    it('get() retrieves nested value', function () {
        $arr = ['db' => ['host' => 'localhost']];
        expect(Arr::get($arr, 'db.host'))->toBe('localhost');
    });

    it('get() returns default for missing key', function () {
        expect(Arr::get([], 'missing.key', 'default'))->toBe('default');
    });

    it('set() writes nested values', function () {
        $arr = [];
        Arr::set($arr, 'a.b.c', 42);
        expect($arr['a']['b']['c'])->toBe(42);
    });

    it('has() detects presence and absence', function () {
        $arr = ['x' => ['y' => 1]];
        expect(Arr::has($arr, 'x.y'))->toBeTrue()
            ->and(Arr::has($arr, 'x.z'))->toBeFalse();
    });

    it('forget() removes a nested key', function () {
        $arr = ['a' => ['b' => 1, 'c' => 2]];
        Arr::forget($arr, 'a.b');
        expect($arr)->toBe(['a' => ['c' => 2]]);
    });
});

describe('Arr — manipulation', function () {
    it('only() keeps specified keys', function () {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        expect(Arr::only($arr, ['a', 'c']))->toBe(['a' => 1, 'c' => 3]);
    });

    it('except() removes specified keys', function () {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        expect(Arr::except($arr, 'b'))->toBe(['a' => 1, 'c' => 3]);
    });

    it('flatten() flattens a nested array', function () {
        $arr    = [1, [2, [3, 4]]];
        expect(Arr::flatten($arr))->toBe([1, 2, 3, 4]);
    });

    it('flatten() respects depth', function () {
        $arr = [1, [2, [3, 4]]];
        expect(Arr::flatten($arr, 1))->toBe([1, 2, [3, 4]]);
    });

    it('pluck() extracts a column', function () {
        $arr = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        expect(Arr::pluck($arr, 'name'))->toBe(['Alice', 'Bob']);
    });

    it('wrap() wraps a scalar in an array', function () {
        expect(Arr::wrap('hello'))->toBe(['hello'])
            ->and(Arr::wrap(['already']))->toBe(['already'])
            ->and(Arr::wrap(null))->toBe([]);
    });

    it('groupBy() groups items by key', function () {
        $arr = [
            ['type' => 'fruit', 'name' => 'apple'],
            ['type' => 'veg',   'name' => 'carrot'],
            ['type' => 'fruit', 'name' => 'banana'],
        ];
        $grouped = Arr::groupBy($arr, 'type');
        expect(count($grouped['fruit']))->toBe(2)
            ->and(count($grouped['veg']))->toBe(1);
    });
});

describe('Arr — inspection', function () {
    it('isAssoc() detects associative arrays', function () {
        expect(Arr::isAssoc(['a' => 1]))->toBeTrue()
            ->and(Arr::isAssoc([1, 2, 3]))->toBeFalse();
    });

    it('isList() detects list arrays', function () {
        expect(Arr::isList([1, 2, 3]))->toBeTrue()
            ->and(Arr::isList(['a' => 1]))->toBeFalse();
    });
});
