<?php

// Base application behavior (openspec change project-foundation, capability app-foundation;
// root redirect from change single-owner-login).

use App\Models\User;

test('a guest opening the root URL is redirected to login', function () {
    $this->get('/')->assertRedirect('/login');
});

test('a signed-in owner opening the root URL is redirected to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect('/dashboard');
});

test('the configured locale is English', function () {
    expect(app()->getLocale())->toBe('en');
});
