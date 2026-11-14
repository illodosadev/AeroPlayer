<?php

// adopt-cqrs, task 1.1: the query bus (design D2). The Unit suite does not boot Laravel, so each test gives the
// bus a plain container. The test classes have names unique to this file: Pest declares them globally.

use App\Bus\HandlerNotFound;
use App\Bus\Query;
use App\Bus\QueryBus;
use Illuminate\Container\Container;

final class BusTestMultiplier
{
    public function __construct(public int $factor = 3) {}
}

/**
 * @implements Query<int>
 */
final readonly class BusTestTriple implements Query
{
    public function __construct(public int $number) {}
}

final readonly class BusTestTripleHandler
{
    public function __construct(private BusTestMultiplier $multiplier) {}

    public function handle(BusTestTriple $query): int
    {
        return $query->number * $this->multiplier->factor;
    }
}

final readonly class BusTestOrphanQuery implements Query
{
    public function __construct(public string $token) {}
}

test('ask returns the handler result, with the handler dependencies injected', function () {
    expect((new QueryBus(new Container))->ask(new BusTestTriple(7)))->toBe(21);
});

test('ask resolves the handler from the given container', function () {
    $container = new Container;
    $container->instance(BusTestMultiplier::class, new BusTestMultiplier(10));

    expect((new QueryBus($container))->ask(new BusTestTriple(7)))->toBe(70);
});

test('a query without a handler throws HandlerNotFound without its payload', function () {
    expect(fn () => (new QueryBus(new Container))->ask(new BusTestOrphanQuery('BQ-secret-token')))
        ->toThrow(function (HandlerNotFound $exception) {
            expect($exception->getMessage())
                ->toContain('BusTestOrphanQueryHandler')
                ->not->toContain('BQ-secret-token');
        });
});
