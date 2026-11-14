<?php

// adopt-cqrs, task 1.1: the command bus (design D2). The Unit suite does not boot Laravel, so each test gives the
// bus a plain container. The test classes have names unique to this file: Pest declares them globally.

use App\Bus\Command;
use App\Bus\CommandBus;
use App\Bus\HandlerNotFound;
use Illuminate\Container\Container;

final class BusTestCommandLog
{
    /** @var list<string> */
    public array $entries = [];
}

final readonly class BusTestRecord implements Command
{
    public function __construct(public string $entry) {}
}

final readonly class BusTestRecordHandler
{
    public function __construct(private BusTestCommandLog $log) {}

    public function handle(BusTestRecord $command): void
    {
        $this->log->entries[] = $command->entry;
    }
}

final readonly class BusTestOrphanCommand implements Command
{
    public function __construct(#[SensitiveParameter] public string $password) {}
}

test('dispatch runs the handler with its injected dependencies', function () {
    $container = new Container;
    $log = new BusTestCommandLog;
    $container->instance(BusTestCommandLog::class, $log);

    $bus = new CommandBus($container);
    $bus->dispatch(new BusTestRecord('first'));
    $bus->dispatch(new BusTestRecord('second'));

    expect($log->entries)->toBe(['first', 'second']);
});

test('dispatch returns nothing', function () {
    expect((string) (new ReflectionMethod(CommandBus::class, 'dispatch'))->getReturnType())->toBe('void');
});

test('a command without a handler throws HandlerNotFound without its payload', function () {
    expect(fn () => (new CommandBus(new Container))->dispatch(new BusTestOrphanCommand('Top-Secret-123')))
        ->toThrow(function (HandlerNotFound $exception) {
            expect($exception->getMessage())
                ->toContain('BusTestOrphanCommandHandler')
                ->not->toContain('Top-Secret-123');
        });
});
