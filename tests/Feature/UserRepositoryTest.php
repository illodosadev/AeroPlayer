<?php

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function sessionRow(int $userId, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

test('findOwner returns null when no account exists', function () {
    expect(app(UserRepository::class)->findOwner())->toBeNull();
});

test('createOwner stores a hashed password and the local part as name', function () {
    $owner = app(UserRepository::class)->createOwner('owner@example.com', 'Correct-Horse-42');

    expect($owner->name)->toBe('owner')
        ->and($owner->email)->toBe('owner@example.com')
        ->and($owner->password)->not->toBe('Correct-Horse-42')
        ->and(Hash::check('Correct-Horse-42', $owner->password))->toBeTrue()
        ->and(User::count())->toBe(1);
});

test('replaceOwner changes the password and remember token and ends only that account sessions', function () {
    $repository = app(UserRepository::class);
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $other = User::factory()->create();
    $oldToken = $owner->remember_token;

    sessionRow($owner->id, 'owner-device-a');
    sessionRow($owner->id, 'owner-device-b');
    sessionRow($other->id, 'other-session');

    $updated = $repository->replaceOwner($owner, 'new@example.com', 'New-Horse-77');

    expect(Hash::check('New-Horse-77', $updated->password))->toBeTrue()
        ->and(Hash::check('Correct-Horse-42', $updated->password))->toBeFalse()
        ->and($updated->email)->toBe('new@example.com')
        ->and($updated->remember_token)->not->toBe($oldToken)
        ->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});
