<?php

namespace App\Bus;

use Illuminate\Contracts\Container\Container;

/**
 * Runs a command through its handler, "<Command>Handler", resolved from the container on every call
 * (adopt-cqrs, design D2). It logs nothing: commands can carry passwords.
 */
final class CommandBus
{
    public function __construct(private readonly Container $container) {}

    public function dispatch(Command $command): void
    {
        $handler = $command::class.'Handler';

        if (! class_exists($handler)) {
            throw HandlerNotFound::for($command);
        }

        $this->container->make($handler)->handle($command);
    }
}
