<?php

declare(strict_types=1);

use Fulcrum\Support\Collection;

describe('Collection — transformations', function () {
    it('map() transforms each item', function () {
        $result = Collection::make([1, 2, 3])->map(fn ($n) => $n * 2);
        expect($result->all())->toBe([2, 4, 6]);
    });

    it('filter() keeps matching items and re-indexes', function () {
        $result = Collection::make([1, 2, 3, 4, 5])->filter(fn ($n) => $n % 2 === 0);
        expect($result->all())->toBe([2, 4]);
    });

    it('filter() with no callback removes falsy values', function () {
        $result = Collection::make([0, 1, '', 'a', null, false, true])->filter();
        expect($result->all())->toBe([1, 'a', true]);
    });

    it('reject() keeps non-matching items', function () {
        $result = Collection::make([1, 2, 3, 4])->reject(fn ($n) => $n > 2);
        expect($result->all())->toBe([1, 2]);
    });

    it('reduce() folds the collection into a single value', function () {
        $sum = Collection::make([1, 2, 3, 4])->reduce(fn ($carry, $n) => $carry + $n, 0);
        expect($sum)->toBe(10);
    });

    it('is immutable — original collection unchanged after map', function () {
        $original = Collection::make([1, 2, 3]);
        $mapped   = $original->map(fn ($n) => $n * 10);

        expect($original->all())->toBe([1, 2, 3])
            ->and($mapped->all())->toBe([10, 20, 30]);
    });
});

describe('Collection — searching', function () {
    it('first() returns the first element', function () {
        expect(Collection::make([5, 10, 15])->first())->toBe(5);
    });

    it('first() returns null for an empty collection', function () {
        expect(Collection::make()->first())->toBeNull();
    });

    it('first() with callback returns first match', function () {
        $result = Collection::make([1, 2, 3, 4])->first(fn ($n) => $n > 2);
        expect($result)->toBe(3);
    });

    it('last() returns the last element', function () {
        expect(Collection::make([5, 10, 15])->last())->toBe(15);
    });

    it('contains() finds a value', function () {
        $c = Collection::make(['a', 'b', 'c']);
        expect($c->contains('b'))->toBeTrue()
            ->and($c->contains('z'))->toBeFalse();
    });
});

describe('Collection — aggregates', function () {
    it('count() returns correct size', function () {
        expect(Collection::make([1, 2, 3])->count())->toBe(3);
    });

    it('isEmpty() and isNotEmpty()', function () {
        expect(Collection::make([])->isEmpty())->toBeTrue()
            ->and(Collection::make([1])->isNotEmpty())->toBeTrue();
    });

    it('sum() sums numeric values', function () {
        expect(Collection::make([1, 2, 3, 4])->sum())->toBe(10);
    });
});

describe('Collection — manipulation', function () {
    it('take() limits the collection', function () {
        expect(Collection::make([1, 2, 3, 4, 5])->take(3)->all())->toBe([1, 2, 3]);
    });

    it('skip() skips N items', function () {
        expect(Collection::make([1, 2, 3, 4, 5])->skip(2)->all())->toBe([3, 4, 5]);
    });

    it('pluck() extracts a column', function () {
        $data   = [['name' => 'Alice'], ['name' => 'Bob']];
        $result = Collection::make($data)->pluck('name');
        expect($result->all())->toBe(['Alice', 'Bob']);
    });

    it('merge() combines two collections', function () {
        $a = Collection::make([1, 2]);
        $b = Collection::make([3, 4]);
        expect($a->merge($b)->all())->toBe([1, 2, 3, 4]);
    });

    it('push() appends a value immutably', function () {
        $a = Collection::make([1, 2]);
        $b = $a->push(3);
        expect($a->all())->toBe([1, 2])
            ->and($b->all())->toBe([1, 2, 3]);
    });

    it('unique() deduplicates values', function () {
        expect(Collection::make([1, 2, 2, 3, 3, 3])->unique()->all())->toBe([1, 2, 3]);
    });

    it('reverse() reverses the order', function () {
        expect(Collection::make([3, 1, 2])->reverse()->all())->toBe([2, 1, 3]);
    });
});
