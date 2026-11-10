<?php

// Covers specs/owner-auth (login, logout, lockout, remember me, removed features).

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function owner(): User
{
    return User::factory()->create([
        'email' => 'owner@example.com',
        'password' => 'Correct-Horse-42',
    ]);
}

function rememberCookie(\Illuminate\Testing\TestResponse $response): ?\Symfony\Component\HttpFoundation\Cookie
{
    return collect($response->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));
}

// --- Login -----------------------------------------------------------------------

test('correct credentials sign in and regenerate the session id', function () {
    owner();
    $this->startSession();
    $before = session()->getId();

    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticated();
    expect(session()->getId())->not->toBe($before);
});

test('the email is case-insensitive at login', function () {
    owner();

    $this->post('/login', ['email' => 'Owner@Example.com', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});

test('wrong password or unknown email keeps the visitor out with a generic error', function (string $email, string $password) {
    owner();

    $this->from('/login')->post('/login', ['email' => $email, 'password' => $password])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest();
})->with([
    'wrong password' => ['owner@example.com', 'wrong'],
    'unknown email' => ['nobody@example.com', 'Correct-Horse-42'],
]);

test('a login with no owner account is not a server error', function () {
    $this->from('/login')->post('/login', ['email' => 'owner@example.com', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an empty email shows the required message', function () {
    $this->from('/login')->post('/login', ['email' => '', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'The email field is required.']);

    $this->assertGuest();
});

// --- Rate limit ------------------------------------------------------------------

test('the sixth attempt within the minute is blocked even with the right password', function () {
    owner();

    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
    }

    $this->from('/login')->post('/login', ['email' => 'owner@example.com', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the lock lifts after 61 seconds', function () {
    owner();

    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
    }

    $this->travel(61)->seconds();

    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'Correct-Horse-42'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});

// --- Remember me ----------------------------------------------------------------

test('remember me checked sets the remember cookie', function () {
    owner();

    $response = $this->post('/login', [
        'email' => 'owner@example.com',
        'password' => 'Correct-Horse-42',
        'remember' => true,
    ]);

    expect(rememberCookie($response))->not->toBeNull();
});

test('remember me not sent sets no remember cookie', function () {
    owner();

    $response = $this->post('/login', ['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    expect(rememberCookie($response))->toBeNull();
});

test('the remember cookie keeps the owner signed in after the session has expired', function () {
    owner();

    $response = $this->post('/login', [
        'email' => 'owner@example.com',
        'password' => 'Correct-Horse-42',
        'remember' => true,
    ]);
    $cookie = rememberCookie($response);

    // A new request with only the remember cookie: no session cookie, as after expiry.
    Auth::forgetGuards();
    $this->withCookie($cookie->getName(), $cookie->getValue())
        ->get('/dashboard')
        ->assertOk();
});

// --- Access and pages ------------------------------------------------------------

test('protected pages redirect a visitor without a session to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('a signed-in owner opening login is sent to the dashboard', function () {
    $this->actingAs(owner())->get('/login')->assertRedirect('/dashboard');
});

test('the login page offers no password reset', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('canResetPassword', false));
});

// --- Logout ----------------------------------------------------------------------

test('logout ends the session, redirects to login and clears the history', function () {
    $this->actingAs(owner())->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect('/login');
});

test('the page after logout asks the browser to clear its history', function () {
    $this->actingAs(owner())->post('/logout');

    $page = $this->get('/login')->viewData('page');

    expect($page['clearHistory'])->toBeTrue();
});

test('the history is encrypted on protected pages', function () {
    $page = $this->actingAs(owner())->get('/dashboard')->viewData('page');

    expect($page['encryptHistory'])->toBeTrue();
});

test('logout accepts only POST', function () {
    $this->actingAs(owner())->get('/logout')->assertStatus(405);

    $this->assertAuthenticated();
});

test('a POST to logout without a valid CSRF token is rejected and keeps the owner signed in', function () {
    // Laravel skips CSRF checks in unit tests; re-enable them for this test only.
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->actingAs(owner())->post('/logout')->assertStatus(419);

    $this->assertAuthenticated();
});

test('logout on one device deletes only that device session row', function () {
    config(['session.driver' => 'database']);
    $owner = owner();

    foreach ([str_repeat('a', 40), str_repeat('b', 40)] as $id) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }

    $this->withCookie(config('session.cookie'), str_repeat('a', 40))
        ->actingAs($owner)
        ->post('/logout')
        ->assertRedirect('/login');

    expect(DB::table('sessions')->where('id', str_repeat('a', 40))->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', str_repeat('b', 40))->exists())->toBeTrue();

    $this->withCookie(config('session.cookie'), str_repeat('b', 40))
        ->actingAs($owner)
        ->get('/dashboard')
        ->assertOk();
});

// --- Removed features -----------------------------------------------------------

test('removed auth and settings routes return 404', function (string $method, string $uri) {
    $this->actingAs(owner());

    $this->call($method, $uri)->assertNotFound();
})->with([
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/forgot-password'],
    ['POST', '/forgot-password'],
    ['GET', '/reset-password/any-token'],
    ['POST', '/reset-password'],
    ['GET', '/verify-email'],
    ['POST', '/email/verification-notification'],
    ['GET', '/confirm-password'],
    ['GET', '/settings'],
    ['GET', '/settings/profile'],
    ['PATCH', '/settings/profile'],
    ['DELETE', '/settings/profile'],
    ['GET', '/settings/password'],
    ['PUT', '/settings/password'],
    ['GET', '/settings/appearance'],
]);

test('a registration attempt creates no user', function () {
    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'Correct-Horse-42',
        'password_confirmation' => 'Correct-Horse-42',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
});

test('no two-factor route is registered', function () {
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toStartWith('two-factor')
            ->and($route->uri())->not->toStartWith('user/two-factor')
            ->and($route->uri())->not->toStartWith('settings/two-factor');

        if ($route->getName()) {
            expect($route->getName())->not->toStartWith('two-factor');
        }
    }
});
