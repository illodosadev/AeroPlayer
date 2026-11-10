<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Covers specs/owner-auth "Owner account is created from the console" and "Replacing the owner account".

test('creates the owner interactively and stores the password hashed', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->expectsOutputToContain('Owner account created for owner@example.com.')
        ->assertExitCode(0);

    $owner = User::query()->sole();
    expect($owner->email)->toBe('owner@example.com')
        ->and($owner->password)->not->toBe('Correct-Horse-42')
        ->and(Hash::check('Correct-Horse-42', $owner->password))->toBeTrue();
});

test('stores the email in lowercase', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', 'Owner@Example.com')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->assertExitCode(0);

    expect(User::query()->sole()->email)->toBe('owner@example.com');
});

test('an invalid email is shown and asked again', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsOutputToContain('The email field must be a valid email address.')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(1);
});

test('an empty email is shown and asked again', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', '')
        ->expectsOutputToContain('The email field is required.')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->assertExitCode(0);
});

test('each invalid password rule shows its message and asks again', function (string $bad, string $message) {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', $bad)
        ->expectsOutputToContain($message)
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(1);
})->with([
    'too short' => ['Short-7a', 'The password field must be at least 12 characters.'],
    'no uppercase' => ['correct-horse-42', 'The password field must contain at least one uppercase and one lowercase letter.'],
    'no symbol' => ['Correcthorse42', 'The password field must contain at least one symbol.'],
    'no number' => ['Correct-Horse-x', 'The password field must contain at least one number.'],
]);

test('a mismatched confirmation shows its message and asks again', function () {
    $this->artisan('app:create-owner')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Other-Horse-99')
        ->expectsOutputToContain('The password field confirmation does not match.')
        ->expectsQuestion('Password', 'Correct-Horse-42')
        ->expectsQuestion('Confirm password', 'Correct-Horse-42')
        ->assertExitCode(0);
});

test('a confirmed replacement updates the account and ends the other device sessions', function () {
    config(['session.driver' => 'database']);

    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);
    DB::table('sessions')->insert([
        'id' => 'other-device',
        'user_id' => $owner->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->artisan('app:create-owner')
        ->expectsConfirmation('An owner account already exists (owner@example.com). Replace its email and password?', 'yes')
        ->expectsQuestion('Email', 'owner@example.com')
        ->expectsQuestion('Password', 'New-Horse-77')
        ->expectsQuestion('Confirm password', 'New-Horse-77')
        ->expectsOutputToContain('Owner account updated for owner@example.com.')
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(1)
        ->and(Hash::check('New-Horse-77', $owner->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0);
});

test('a declined replacement changes nothing', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    $this->artisan('app:create-owner')
        ->expectsConfirmation('An owner account already exists (owner@example.com). Replace its email and password?', 'no')
        ->expectsOutputToContain('Nothing changed.')
        ->assertExitCode(1);

    expect(Hash::check('Correct-Horse-42', $owner->fresh()->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

test('without an interactive terminal the command does nothing', function (bool $existingOwner) {
    if ($existingOwner) {
        User::factory()->create(['email' => 'owner@example.com']);
    }
    $before = User::query()->pluck('email')->all();

    $this->artisan('app:create-owner', ['--no-interaction' => true])
        ->expectsOutputToContain('This command needs an interactive terminal.')
        ->assertExitCode(1);

    expect(User::query()->pluck('email')->all())->toBe($before);
})->with([
    'empty users table' => [false],
    'existing owner' => [true],
]);
