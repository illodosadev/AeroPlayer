<?php

namespace App\Bus;

use Illuminate\Contracts\Container\Container;

/**
 * Answers a query through its handler, "<Query>Handler", resolved from the container on every call
 * (adopt-cqrs, design D2).
 */
final class QueryBus
{
    public function __construct(private readonly Container $container) {}

    /**
     * @template TResult
     *
     * @param  Query<TResult>  $query
     * @return TResult
     */
    public function ask(Query $query): mixed
    {
        $handler = $query::class.'Handler';

        if (! class_exists($handler)) {
            throw HandlerNotFound::for($query);
        }

        return $this->container->make($handler)->handle($query);
    }
}
