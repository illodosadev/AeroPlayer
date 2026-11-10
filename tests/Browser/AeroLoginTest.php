<?php

// Aero login (aero-design-system, tasks 4.1 and 4.2): spec aero-design, "Aero login page" and "Light mode only".

use App\Models\User;

test('the login is built from the Aero components and no starter-kit component', function () {
    $hooks = visit('/login')->script('({
        background: !!document.querySelector("[data-aero=background]"),
        panel: !!document.querySelector("[data-aero=glass-panel]"),
        email: !!document.querySelector("[data-aero=input] #email"),
        password: !!document.querySelector("[data-aero=input] #password"),
        rememberUnchecked: document.querySelector("[data-aero=checkbox] #remember")?.checked === false,
        button: [...document.querySelectorAll("[data-aero=button]")].some((b) => b.textContent.trim() === "Log in"),
        slots: document.querySelectorAll("[data-slot]").length,
    })');

    expect($hooks)->toBe([
        'background' => true,
        'panel' => true,
        'email' => true,
        'password' => true,
        'rememberUnchecked' => true,
        'button' => true,
        'slots' => 0,
    ]);
});

test('the Aero login still signs the owner in', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->assertPathIs('/dashboard');
});

test('the credentials error is shown inside the glass panel and is readable', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    $page = visit('/login');
    $page->script('document.documentElement.classList.add("aero-no-blur")');
    $page->fill('email', 'owner@example.com')
        ->fill('password', 'wrong-password')
        ->press('Log in')
        ->assertSee('These credentials do not match our records.');

    expect($page->script('document.querySelector("[data-aero=glass-panel] #email-error")?.textContent.trim()'))
        ->toBe('These credentials do not match our records.');
    expect($page->script(aeroContrastRatio('#email-error', '[data-aero=glass-panel]')))->toBeGreaterThanOrEqual(4.5);
});

test('a stray dark class does not change the Aero login', function () {
    $page = visit('/login');
    $panelBackground = 'getComputedStyle(document.querySelector("[data-aero=glass-panel]")).backgroundColor';

    $before = $page->script($panelBackground);
    $page->script('document.documentElement.classList.add("dark")');

    expect($page->script($panelBackground))->toBe($before);

    $page->script('document.documentElement.classList.add("aero-no-blur")');
    expect($page->script(aeroContrastRatio('[data-aero=glass-panel]')))->toBeGreaterThanOrEqual(4.5);
});
