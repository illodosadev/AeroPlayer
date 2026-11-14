<?php

// The three browser checks single-owner-login made by hand (aero-design-system, task 6.2, design D12).
// Requirements: openspec/specs/owner-auth.

use App\Models\User;

function ownerOnDashboard()
{
    User::factory()->create(['name' => 'Owner Person', 'email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    return visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->assertPathIs('/dashboard');
}

test('the login page shows no sign-up or password reset link', function () {
    visit('/login')
        ->assertDontSee('Register')
        ->assertDontSee('Sign up')
        ->assertDontSee('Forgot password?');
});

test('the user menu links lead to existing pages only', function () {
    $page = ownerOnDashboard()->click('Owner Person')->wait(1);

    $items = $page->script('[...document.querySelectorAll("[role=menu] [role=menuitem], [role=menu] a")].map((el) => ({ text: el.textContent.trim(), href: el.getAttribute("href") }))');

    expect($items)->not->toBeEmpty();

    // The "Settings" item leads to the Spotify settings (spotify-connection, owner-auth delta).
    $settings = collect($items)->firstWhere('text', 'Settings');
    expect($settings)->not->toBeNull()
        ->and(parse_url($settings['href'], PHP_URL_PATH))->toBe('/settings/spotify');

    foreach ($items as $item) {
        if ($item['href'] === null || str_contains(strtolower($item['text']), 'log out')) {
            continue;
        }

        expect(parse_url($item['href'], PHP_URL_PATH) ?? '')->not->toBeIn(['/settings/profile', '/settings/password', '/settings/appearance']);
        expect($page->script('fetch("'.$item['href'].'").then((r) => r.status)'))->not->toBe(404);
    }
});

test('going back after logout shows no protected page', function () {
    ownerOnDashboard()
        ->click('Owner Person')
        ->wait(1)
        ->click('Log out')
        ->wait(2)
        ->assertPathIs('/login')
        ->back()
        ->wait(2)
        ->assertPathIs('/login')
        ->assertDontSee('Dashboard');
});
