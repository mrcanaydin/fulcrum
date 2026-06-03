<?php

declare(strict_types=1);

use Fulcrum\Container\Container;
use Fulcrum\Container\Exceptions\ContainerException;
use Fulcrum\Container\Exceptions\NotFoundException;

// ─── Fixtures ────────────────────────────────────────────────────────────────

class SimpleService
{
    public string $value = 'simple';
}

class DependentService
{
    public function __construct(public readonly SimpleService $simple) {}
}

class DeepService
{
    public function __construct(public readonly DependentService $dependent) {}
}

interface ContractInterface {}

class ConcreteImpl implements ContractInterface
{
    public string $tag = 'concrete';
}

class PrimitiveService
{
    public function __construct(public readonly string $name) {}
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Container — bind()', function () {
    it('resolves a transient binding', function () {
        $container = new Container();
        $container->bind(SimpleService::class, SimpleService::class);

        $a = $container->make(SimpleService::class);
        $b = $container->make(SimpleService::class);

        expect($a)->toBeInstanceOf(SimpleService::class)
            ->and($a)->not->toBe($b); // transient → different instances
    });

    it('resolves via a factory callable', function () {
        $container = new Container();
        $container->bind(SimpleService::class, fn () => new SimpleService());

        expect($container->make(SimpleService::class))->toBeInstanceOf(SimpleService::class);
    });
});

describe('Container — singleton()', function () {
    it('returns the same instance on repeated calls', function () {
        $container = new Container();
        $container->singleton(SimpleService::class, SimpleService::class);

        $a = $container->make(SimpleService::class);
        $b = $container->make(SimpleService::class);

        expect($a)->toBe($b);
    });

    it('can bind an interface to a concrete singleton', function () {
        $container = new Container();
        $container->singleton(ContractInterface::class, ConcreteImpl::class);

        $instance = $container->make(ContractInterface::class);
        expect($instance)->toBeInstanceOf(ConcreteImpl::class);
        expect($container->make(ContractInterface::class))->toBe($instance);
    });
});

describe('Container — instance()', function () {
    it('returns the exact pre-built value', function () {
        $container = new Container();
        $obj       = new SimpleService();
        $container->instance(SimpleService::class, $obj);

        expect($container->make(SimpleService::class))->toBe($obj);
    });
});

describe('Container — alias()', function () {
    it('resolves the abstract via its alias', function () {
        $container = new Container();
        $container->singleton(SimpleService::class, SimpleService::class);
        $container->alias(SimpleService::class, 'simple');

        expect($container->make('simple'))->toBeInstanceOf(SimpleService::class);
    });
});

describe('Container — auto-wiring', function () {
    it('auto-wires a class with no constructor', function () {
        $container = new Container();
        expect($container->make(SimpleService::class))->toBeInstanceOf(SimpleService::class);
    });

    it('auto-wires a single dependency', function () {
        $container = new Container();
        $service   = $container->make(DependentService::class);

        expect($service)->toBeInstanceOf(DependentService::class)
            ->and($service->simple)->toBeInstanceOf(SimpleService::class);
    });

    it('resolves a deep nested dependency graph', function () {
        $container = new Container();
        $service   = $container->make(DeepService::class);

        expect($service->dependent)->toBeInstanceOf(DependentService::class)
            ->and($service->dependent->simple)->toBeInstanceOf(SimpleService::class);
    });

    it('throws ContainerException for unresolvable primitives', function () {
        $container = new Container();
        $container->make(PrimitiveService::class);
    })->throws(ContainerException::class);
});

describe('Container — PSR-11', function () {
    it('has() returns true for bound and auto-wireable classes', function () {
        $container = new Container();
        $container->singleton(SimpleService::class, SimpleService::class);

        expect($container->has(SimpleService::class))->toBeTrue()
            ->and($container->has(DependentService::class))->toBeTrue()  // auto-wireable
            ->and($container->has('NonExistentClass'))->toBeFalse();
    });

    it('get() throws NotFoundException for unknown entries', function () {
        $container = new Container();
        $container->get('NonExistentClass\That\DoesNotExist');
    })->throws(NotFoundException::class);

    it('bound() only returns true for explicit bindings', function () {
        $container = new Container();
        $container->singleton(SimpleService::class, SimpleService::class);

        expect($container->bound(SimpleService::class))->toBeTrue()
            ->and($container->bound(DependentService::class))->toBeFalse();
    });
});

describe('Container — make() with explicit parameters', function () {
    it('injects explicit primitive parameters', function () {
        $container = new Container();
        $service   = $container->make(PrimitiveService::class, ['name' => 'Fulcrum']);

        expect($service->name)->toBe('Fulcrum');
    });
});
