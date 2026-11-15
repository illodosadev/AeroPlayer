<?php

// youtube-music-connection, tasks 1.1 and 2.1 (design D1 and D3): the device flow service against faked Google
// answers. No test reaches Google.

use App\YouTubeMusic\DeviceCode;
use App\YouTubeMusic\GoogleDeviceFlow;
use App\YouTubeMusic\GoogleTokens;
use App\YouTubeMusic\TokenPollWait;
use App\YouTubeMusic\YouTubeChannel;
use App\YouTubeMusic\YouTubeMusicConnectFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    configureYouTubeMusic();
});

function deviceFlow(): GoogleDeviceFlow
{
    return app(GoogleDeviceFlow::class);
}

test('YouTube Music is configured when the client id, the secret and the owner channel id are set', function () {
    expect(deviceFlow()->isConfigured())->toBeTrue();
});

test('YouTube Music is not configured when one setting is missing', function (string $key) {
    config(["services.youtube_music.{$key}" => '']);

    expect(deviceFlow()->isConfigured())->toBeFalse();
})->with(['client_id', 'client_secret', 'owner_channel_id']);

// --- Device code -----------------------------------------------------------------------------------------------

test('the device code request sends the client id and only the read-only scope', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(googleFixture('device-code'))]);

    $code = deviceFlow()->requestDeviceCode();

    expect($code)->toBeInstanceOf(DeviceCode::class)
        ->and($code->deviceCode)->toBe('dev-123')
        ->and($code->userCode)->toBe('ABCD-EFGH')
        ->and($code->verificationUrl)->toBe('https://www.google.com/device')
        ->and($code->interval)->toBe(5)
        ->and($code->expiresIn)->toBe(1800);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === GoogleDeviceFlow::DEVICE_CODE_URL
        && $request->data() === ['client_id' => 'client-abc', 'scope' => 'https://www.googleapis.com/auth/youtube.readonly']);
});

test('a device code request Google cannot serve is unreachable', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => $answer]);

    expect(deviceFlow()->requestDeviceCode())->toBe(YouTubeMusicConnectFailure::Unreachable);
})->with([
    'a server error' => fn () => Http::response(['error' => 'backend'], 503),
    'a rate limit' => fn () => Http::response([], 429),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

test('a rejected client on the device code request is a configuration error', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(['error' => 'invalid_client'], 401)]);

    expect(deviceFlow()->requestDeviceCode())->toBe(YouTubeMusicConnectFailure::Misconfigured);
});

test('any other refusal of the device code request is rejected', function () {
    Http::fake([GoogleDeviceFlow::DEVICE_CODE_URL => Http::response(['error' => 'invalid_scope'], 400)]);

    expect(deviceFlow()->requestDeviceCode())->toBe(YouTubeMusicConnectFailure::Rejected);
});

// --- Token poll ------------------------------------------------------------------------------------------------

test('an approved code returns the tokens, and the poll sends the device grant', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(googleFixture('token'))]);

    $tokens = deviceFlow()->pollToken('dev-123');

    expect($tokens)->toBeInstanceOf(GoogleTokens::class)
        ->and($tokens->accessToken)->toBe('ya29.access-123')
        ->and($tokens->refreshToken)->toBe('1//refresh-456')
        ->and($tokens->expiresIn)->toBe(3599)
        ->and($tokens->scope)->toBe(GoogleDeviceFlow::SCOPE);

    Http::assertSent(fn (Request $request) => $request->url() === GoogleDeviceFlow::TOKEN_URL && $request->data() === [
        'client_id' => 'client-abc',
        'client_secret' => 'secret-xyz',
        'device_code' => 'dev-123',
        'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
    ]);
});

test('each token answer maps to its outcome', function (mixed $answer, mixed $outcome) {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => $answer]);

    expect(deviceFlow()->pollToken('dev-123'))->toBe($outcome);
})->with([
    'pending' => [fn () => Http::response(['error' => 'authorization_pending'], 428), TokenPollWait::Pending],
    'slow down' => [fn () => Http::response(['error' => 'slow_down'], 403), TokenPollWait::SlowDown],
    'a rate limit' => [fn () => Http::response([], 429), TokenPollWait::SlowDown],
    'a server error' => [fn () => Http::response([], 503), TokenPollWait::Retry],
    'no answer' => [fn () => fn () => throw new ConnectionException('timed out'), TokenPollWait::Retry],
    'denied' => [fn () => Http::response(['error' => 'access_denied'], 403), YouTubeMusicConnectFailure::Denied],
    'expired' => [fn () => Http::response(['error' => 'expired_token'], 400), YouTubeMusicConnectFailure::Expired],
    'a rejected client' => [fn () => Http::response(['error' => 'invalid_client'], 401), YouTubeMusicConnectFailure::Misconfigured],
    'another refusal' => [fn () => Http::response(['error' => 'invalid_request'], 400), YouTubeMusicConnectFailure::Rejected],
]);

// --- Channel ---------------------------------------------------------------------------------------------------

test('the channel request reads the account channel with part=snippet and mine=true', function () {
    Http::fake([GoogleDeviceFlow::CHANNELS_URL.'*' => Http::response(googleFixture('channels-owner'))]);

    $channel = deviceFlow()->fetchChannel('ya29.access-123');

    expect($channel)->toEqual(new YouTubeChannel('UC-owner', 'Alice Music'));

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), GoogleDeviceFlow::CHANNELS_URL.'?')
        && $request['part'] === 'snippet'
        && $request['mine'] === 'true'
        && $request->hasHeader('Authorization', 'Bearer ya29.access-123'));
});

test('an account without a channel has no channel', function () {
    Http::fake([GoogleDeviceFlow::CHANNELS_URL.'*' => Http::response(googleFixture('channels-none'))]);

    expect(deviceFlow()->fetchChannel('ya29.access-123'))->toBeNull();
});

test('a channel request that fails cannot verify the account', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::CHANNELS_URL.'*' => $answer]);

    expect(deviceFlow()->fetchChannel('ya29.access-123'))->toBe(YouTubeMusicConnectFailure::ChannelUnverifiable);
})->with([
    'a server error' => fn () => Http::response([], 503),
    'a refusal' => fn () => Http::response([], 403),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

// --- Revocation ------------------------------------------------------------------------------------------------

test('revoking posts the token to the revocation endpoint', function () {
    Http::fake([GoogleDeviceFlow::REVOKE_URL => Http::response([])]);

    deviceFlow()->revoke('1//refresh-456');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === GoogleDeviceFlow::REVOKE_URL
        && $request->data() === ['token' => '1//refresh-456']);
});

test('a revocation that fails or does not answer is ignored', function (mixed $answer) {
    Http::fake([GoogleDeviceFlow::REVOKE_URL => $answer]);

    expect(fn () => deviceFlow()->revoke('1//refresh-456'))->not->toThrow(Throwable::class);
})->with([
    'a server error' => fn () => Http::response([], 503),
    'no answer' => fn () => fn () => throw new ConnectionException('timed out'),
]);

// --- Renewal (youtube-music-credentials, task 2.1) ------------------------------------------------------------

test('the renewal posts the refresh token with the client credentials', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['access_token' => 'ya29.new-789', 'expires_in' => 3599])]);

    $response = deviceFlow()->requestRefresh('1//refresh-456');

    expect($response->json('access_token'))->toBe('ya29.new-789');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === GoogleDeviceFlow::TOKEN_URL
        && $request->data() === [
            'client_id' => 'client-abc',
            'client_secret' => 'secret-xyz',
            'refresh_token' => '1//refresh-456',
            'grant_type' => 'refresh_token',
        ]);
});

test('a renewal Google cannot serve comes back as the response, without throwing', function () {
    Http::fake([GoogleDeviceFlow::TOKEN_URL => Http::response(['error' => 'upstream-body-detail'], 503)]);

    expect(deviceFlow()->requestRefresh('1//refresh-456')->status())->toBe(503);
});

test('every token parameter is sensitive', function () {
    $sensitive = fn (string $method, string $name) => (new ReflectionParameter([GoogleDeviceFlow::class, $method], $name))
        ->getAttributes(SensitiveParameter::class) !== [];

    expect($sensitive('pollToken', 'deviceCode'))->toBeTrue()
        ->and($sensitive('fetchChannel', 'accessToken'))->toBeTrue()
        ->and($sensitive('revoke', 'token'))->toBeTrue()
        ->and($sensitive('requestRefresh', 'refreshToken'))->toBeTrue();
});
