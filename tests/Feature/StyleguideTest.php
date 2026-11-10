<?php

// Styleguide page access (aero-design-system, spec aero-design, "Styleguide page").

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner opens the styleguide', function () {
    $this->actingAs(User::factory()->create())
        ->get('/styleguide')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Styleguide'));
});

test('a visitor without a session is redirected to login from the styleguide', function () {
    $this->get('/styleguide')->assertRedirect('/login');
});
