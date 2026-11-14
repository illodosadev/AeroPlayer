<?php

namespace App\Application\Library\Commands;

use App\Application\Library\LibraryCache;
use App\Repositories\SpotifyConnectionRepository;
use Illuminate\Support\Facades\Cache;

final readonly class RefreshLibraryHandler
{
    public function __construct(private SpotifyConnectionRepository $connections) {}

    /**
     * Forget the cached library of the current connection. Without a connection there is nothing to forget.
     */
    public function handle(RefreshLibrary $command): void
    {
        $connection = $this->connections->current();

        if ($connection !== null) {
            Cache::forget(LibraryCache::key($connection->connection_id));
        }
    }
}
