<?php

// Sky theme and light mode only (aero-design-system, tasks 2.1 and 2.2).

use App\Models\User;

beforeEach(function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);
});

test('pages carry the default theme', function () {
    visit('/login')->assertScript('document.documentElement.dataset.theme === "sky"');
});

test('the system prefers dark and the login page stays light', function () {
    visit('/login')->inDarkMode()->assertScript('document.documentElement.classList.contains("dark")', false);
});

test('a stored starter-kit preference of dark does not darken the login page', function () {
    $page = visit('/login');
    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->refresh();
    $page->assertScript('document.documentElement.classList.contains("dark")', false);
});

test('the dashboard stays light when the system prefers dark', function () {
    visit('/login')
        ->inDarkMode()
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate('/dashboard')
        ->assertScript('document.documentElement.classList.contains("dark")', false);
});

test('the dashboard stays light with a stored dark preference', function () {
    $page = visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate('/dashboard');
    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->refresh();
    $page->assertScript('document.documentElement.classList.contains("dark")', false);
});

test('the styleguide stays light when the system prefers dark', function () {
    visit('/login')
        ->inDarkMode()
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate('/styleguide')
        ->assertScript('document.documentElement.classList.contains("dark")', false);
});

test('the styleguide stays light with a stored dark preference', function () {
    $page = visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate('/styleguide');
    $page->script("localStorage.setItem('appearance', 'dark')");
    $page->refresh();
    $page->assertScript('document.documentElement.classList.contains("dark")', false);
});
