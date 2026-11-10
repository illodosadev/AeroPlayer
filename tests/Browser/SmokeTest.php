<?php

// Browser harness smoke tests (aero-design-system, tasks 1.1 and 1.2).

use App\Models\User;

test('the login page renders in a real browser', function () {
    visit('/login')->assertSee('Log in');
});

test('the owner signs in and reaches the dashboard in one browser session', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    // Each visit() call starts a new browser context, so the sign-in and the next page share one chain.
    visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate('/dashboard')
        ->assertPathIs('/dashboard');
});
