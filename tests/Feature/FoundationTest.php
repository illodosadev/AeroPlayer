<?php

// Base application behavior (openspec change project-foundation, capability app-foundation).

use Inertia\Testing\AssertableInertia as Assert;

test('the root URL returns the Welcome page', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('the configured locale is English', function () {
    expect(app()->getLocale())->toBe('en');
});
