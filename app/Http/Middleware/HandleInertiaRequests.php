<?php

namespace App\Http\Middleware;

use App\Application\Spotify\Queries\GetSpotifyStatus;
use App\Application\YouTubeMusic\Queries\GetYouTubeMusicStatus;
use App\Bus\QueryBus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // The connection status only, never a token (spotify-connection, design D10). Guests skip the query.
            'spotify' => function () use ($request) {
                $status = $request->user() ? app(QueryBus::class)->ask(new GetSpotifyStatus)?->value : null;

                return [
                    'status' => $status ?? 'not_connected',
                    'connected' => $status === 'connected',
                    // Where the browser player loads Spotify's SDK from: configuration, signed-in pages only
                    // (spotify-web-player, design D11).
                    ...($request->user() ? ['sdkUrl' => (string) config('services.spotify.sdk_url')] : []),
                ];
            },
            // The connection status only, never a token (youtube-music-connection, design D10). Guests skip the query.
            'youtubeMusic' => function () use ($request) {
                $status = $request->user() ? app(QueryBus::class)->ask(new GetYouTubeMusicStatus)?->value : null;

                return [
                    'status' => $status ?? 'not_connected',
                    'connected' => $status === 'connected',
                ];
            },
        ]);
    }
}
