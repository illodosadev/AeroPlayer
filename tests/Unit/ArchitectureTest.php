<?php

// adopt-cqrs, design D7: the CQRS rule, enforced. Each check collects every problem and compares the list with [],
// so a failure names all offending classes at once.

use App\Application\UseCaseFailed;
use App\Bus\Command;
use App\Bus\Query;
use Illuminate\Database\Eloquent\Model;

/**
 * Every class under app/Application, by its PSR-4 name.
 *
 * @return list<class-string>
 */
function cqrsApplicationClasses(): array
{
    $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Application';
    $classes = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1, -4);
        $classes[] = 'App\\Application\\'.str_replace(['/', '\\'], '\\', $relative);
    }

    sort($classes);

    return $classes;
}

/**
 * The classes of one kind of folder ("Commands", "Queries", "Exceptions" or "Results"), with or without handlers.
 *
 * @return list<class-string>
 */
function cqrsClassesIn(string $folder, bool $handlers): array
{
    return array_values(array_filter(
        cqrsApplicationClasses(),
        fn (string $class) => str_contains($class, "\\{$folder}\\") && str_ends_with($class, 'Handler') === $handlers,
    ));
}

/**
 * The names of the classes in a return type, or the type itself for a builtin such as "void" or "int".
 *
 * @return list<string>
 */
function cqrsTypeNames(?ReflectionType $type): array
{
    if ($type === null) {
        return [];
    }

    if ($type instanceof ReflectionNamedType) {
        return [$type->getName()];
    }

    return array_merge(...array_map(fn (ReflectionType $part) => cqrsTypeNames($part), $type->getTypes()));
}

/**
 * Problems shared by commands and queries: the marker, final readonly, and a handler whose handle() takes the
 * message.
 *
 * @return list<string>
 */
function cqrsMessageProblems(string $class, string $marker): array
{
    $problems = [];
    $message = new ReflectionClass($class);

    if (! $message->implementsInterface($marker)) {
        $problems[] = "{$class} does not implement {$marker}";
    }

    if (! $message->isFinal() || ! $message->isReadOnly()) {
        $problems[] = "{$class} is not final readonly";
    }

    if (! class_exists($class.'Handler')) {
        return [...$problems, "{$class} has no {$class}Handler"];
    }

    $handle = (new ReflectionClass($class.'Handler'))->hasMethod('handle')
        ? new ReflectionMethod($class.'Handler', 'handle')
        : null;

    if ($handle === null || ! $handle->isPublic() || $handle->getNumberOfParameters() !== 1
        || cqrsTypeNames($handle->getParameters()[0]->getType()) !== [$class]) {
        $problems[] = "{$class}Handler has no public handle({$class})";
    }

    return $problems;
}

test('every command has a handler that returns nothing', function () {
    $problems = [];

    foreach (cqrsClassesIn('Commands', handlers: false) as $class) {
        $problems = [...$problems, ...cqrsMessageProblems($class, Command::class)];

        if (method_exists($class.'Handler', 'handle')
            && cqrsTypeNames((new ReflectionMethod($class.'Handler', 'handle'))->getReturnType()) !== ['void']) {
            $problems[] = "{$class}Handler::handle() does not return void";
        }
    }

    expect($problems)->toBe([]);
});

test('every query has a handler that returns data and never a model', function () {
    $problems = [];

    foreach (cqrsClassesIn('Queries', handlers: false) as $class) {
        $problems = [...$problems, ...cqrsMessageProblems($class, Query::class)];

        if (! method_exists($class.'Handler', 'handle')) {
            continue;
        }

        $returns = cqrsTypeNames((new ReflectionMethod($class.'Handler', 'handle'))->getReturnType());

        if ($returns === [] || $returns === ['void'] || $returns === ['mixed']) {
            $problems[] = "{$class}Handler::handle() declares no data return type";
        }

        foreach ($returns as $type) {
            if (class_exists($type) && is_a($type, Model::class, true)) {
                $problems[] = "{$class}Handler::handle() returns the model {$type}";
            }
        }
    }

    expect($problems)->toBe([]);
});

test('every handler belongs to a message', function () {
    $problems = [];

    foreach ([...cqrsClassesIn('Commands', handlers: true), ...cqrsClassesIn('Queries', handlers: true)] as $handler) {
        if (! class_exists(substr($handler, 0, -strlen('Handler')))) {
            $problems[] = "{$handler} has no message";
        }
    }

    expect($problems)->toBe([]);
});

test('use-case failures extend UseCaseFailed', function () {
    $problems = [];

    foreach (cqrsClassesIn('Exceptions', handlers: false) as $class) {
        if (! is_subclass_of($class, UseCaseFailed::class)) {
            $problems[] = "{$class} does not extend ".UseCaseFailed::class;
        }
    }

    expect($problems)->toBe([]);
});

arch('the application layer stays out of HTTP')
    ->expect('App\Application')
    ->not->toUse(['Illuminate\Http\Request', 'Inertia', 'App\Http']);

arch('the buses are final')
    ->expect(['App\Bus\CommandBus', 'App\Bus\QueryBus', 'App\Bus\HandlerNotFound'])
    ->toBeFinal();

// Entry points only validate input, handle HTTP or console concerns and dispatch (design D5 and D7). The arch
// plugin sees class references only: helpers such as auth(), app('db') or request() are left to review.
arch('entry points reach data only through the buses')
    ->expect([
        'App\Http\Controllers',
        'App\Http\Middleware',
        'App\Http\Requests',
        'App\Console',
    ])
    ->not->toUse([
        'App\Repositories',
        'App\Models',
        'App\Spotify\SpotifyOAuth',
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Auth',
        'Illuminate\Support\Facades\RateLimiter',
    ]);
