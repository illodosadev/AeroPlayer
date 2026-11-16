<?php

// single-action-controllers, design D5: every web route runs one invokable controller, and the route table stays as
// it was before the refactor. Laravel's own routes ("up", "storage/{path}") are not in the web group.

use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The routes of the web middleware group.
 *
 * @return list<RoutingRoute>
 */
function webRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => in_array('web', $route->middleware(), true),
    ));
}

test('the web route table keeps its methods, URIs, names and middleware', function () {
    $table = array_map(fn (RoutingRoute $route) => [
        implode('|', $route->methods()),
        $route->uri(),
        $route->getName(),
        $route->middleware(),
    ], webRoutes());

    usort($table, fn (array $a, array $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

    expect($table)->toBe([
        ['GET|HEAD', '/', 'home', ['web']],
        ['GET|HEAD', 'dashboard', 'dashboard', ['web', 'auth']],
        ['GET|HEAD', 'library', 'library', ['web', 'auth']],
        ['GET|HEAD', 'library/liked', 'library.liked', ['web', 'auth']],
        ['GET|HEAD', 'library/liked/songs', 'library.liked.songs', ['web', 'auth']],
        ['GET|HEAD', 'library/playlists/spotify/{id}', 'library.playlist', ['web', 'auth']],
        ['GET|HEAD', 'library/playlists/spotify/{id}/songs', 'library.playlist.songs', ['web', 'auth']],
        ['POST', 'library/refresh', 'library.refresh', ['web', 'auth']],
        ['GET|HEAD', 'login', 'login', ['web', 'guest']],
        ['POST', 'login', null, ['web', 'guest']],
        ['POST', 'logout', 'logout', ['web', 'auth']],
        ['GET|HEAD', 'queue', 'queue.show', ['web', 'auth']],
        ['PATCH', 'queue', 'queue.update', ['web', 'auth']],
        ['PUT', 'queue', 'queue.replace', ['web', 'auth']],
        ['POST', 'queue/items', 'queue.items.add', ['web', 'auth']],
        ['DELETE', 'queue/items/{item}', 'queue.items.remove', ['web', 'auth']],
        ['PATCH', 'queue/items/{item}', 'queue.items.move', ['web', 'auth']],
        ['GET|HEAD', 'search', 'search', ['web', 'auth']],
        ['GET|HEAD', 'search/results', 'search.results', ['web', 'auth']],
        ['GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS', 'settings', 'settings', ['web', 'auth']],
        ['GET|HEAD', 'settings/spotify', 'spotify.settings', ['web', 'auth']],
        ['GET|HEAD', 'settings/youtube-music', 'youtube-music.settings', ['web', 'auth']],
        ['GET|HEAD', 'spotify/callback', 'spotify.callback', ['web', 'auth']],
        ['GET|HEAD', 'spotify/connect', 'spotify.connect', ['web', 'auth']],
        ['DELETE', 'spotify/connection', 'spotify.disconnect', ['web', 'auth']],
        ['PUT', 'spotify/player/play', 'spotify.player.play', ['web', 'auth']],
        ['GET|HEAD', 'spotify/token', 'spotify.token', ['web', 'auth']],
        ['GET|HEAD', 'styleguide', 'styleguide', ['web', 'auth']],
        ['POST', 'youtube-music/connect', 'youtube-music.connect', ['web', 'auth']],
        ['GET|HEAD', 'youtube-music/connect/status', 'youtube-music.connect.status', ['web', 'auth']],
        ['DELETE', 'youtube-music/connection', 'youtube-music.disconnect', ['web', 'auth']],
    ]);
});

test('every web route runs __invoke on an app controller, or is a declarative redirect', function () {
    $problems = [];

    foreach (webRoutes() as $route) {
        // Route::redirect() stores the class with a leading backslash.
        $controller = ltrim((string) $route->getControllerClass(), '\\') ?: null;

        if ($controller === RedirectController::class) {
            continue;
        }

        // An invokable controller is registered by its class alone, so "uses" has no "@method" part.
        $uses = $route->getAction('uses');
        $method = is_string($uses) ? Str::parseCallback($uses, '__invoke')[1] : null;

        if ($controller === null || ! str_starts_with($controller, 'App\\Http\\Controllers\\') || $method !== '__invoke') {
            $problems[] = implode('|', $route->methods()).' '.$route->uri().' runs '.$route->getActionName();
        }
    }

    expect($problems)->toBe([]);
});
