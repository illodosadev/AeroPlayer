<?php

// spotify-playback-api, task 1.2 (design D6): JSON answers for a missing or expired session on the browser player's
// endpoints. These go through the exception handler directly: Laravel skips the CSRF check in tests, and the HTTP
// 401s of each endpoint are covered by its own test once its route exists.

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

function playbackAuthRender(string $method, string $uri, Throwable $exception, bool $json = true): Response
{
    $request = Request::create($uri, $method, server: $json ? ['HTTP_ACCEPT' => 'application/json'] : []);

    return app(ExceptionHandler::class)->render($request, $exception);
}

test('a guest JSON request to either endpoint gets the JSON 401', function (string $method, string $uri) {
    $response = playbackAuthRender($method, $uri, new AuthenticationException);

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true))->toBe(['error' => 'unauthenticated'])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    ['GET', '/spotify/token'],
    ['PUT', '/spotify/player/play'],
]);

test('An expired session on the start endpoint', function () {
    $response = playbackAuthRender('PUT', '/spotify/player/play', new TokenMismatchException('CSRF token mismatch.'));

    expect($response->getStatusCode())->toBe(419)
        ->and(json_decode($response->getContent(), true))->toBe(['error' => 'session_expired']);
});

test('other routes keep Laravel\'s answers', function () {
    $unauthenticated = playbackAuthRender('GET', '/settings/spotify', new AuthenticationException);
    $mismatch = playbackAuthRender('POST', '/logout', new TokenMismatchException('CSRF token mismatch.'));
    $notJson = playbackAuthRender('PUT', '/spotify/player/play', new TokenMismatchException('CSRF token mismatch.'), json: false);

    expect(json_decode($unauthenticated->getContent(), true))->toBe(['message' => 'Unauthenticated.'])
        ->and($mismatch->getStatusCode())->toBe(419)
        ->and(json_decode($mismatch->getContent(), true))->not->toHaveKey('error')
        ->and($notJson->getStatusCode())->toBe(419)
        ->and($notJson->getContent())->not->toContain('session_expired');
});
