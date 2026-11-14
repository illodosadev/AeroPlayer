<?php

// Accessibility and adaptation (aero-design-system, tasks 5.1 to 5.4): spec aero-design, "Visible keyboard focus",
// "Reduced motion", "Mobile layout" and "Background image fallback". The focus walk and the mobile check live in
// tests/Pest.php, so other pages' browser tests can reuse them (spotify-connection, design D12).

use App\Models\User;

function aeroOwnerAt(string $path, array $options = [])
{
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    return visit('/login', $options)
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path);
}

// --- 5.1 Visible keyboard focus ---------------------------------------------------

test('every control on the login shows a visible focus outline and none is skipped', function () {
    aeroAssertFocusWalk(visit('/login'), '#auth-title');
});

test('every control on the styleguide shows a visible focus outline and none is skipped', function () {
    aeroAssertFocusWalk(signedInOwnerVisit('/styleguide'), '#styleguide-title');
});

// --- 5.2 Reduced motion -----------------------------------------------------------

const AERO_MOTION_PROBLEMS = '(() => {
    const problems = [];
    for (const el of document.querySelectorAll("*")) {
        for (const pseudo of [null, "::before", "::after"]) {
            const s = getComputedStyle(el, pseudo);
            const names = s.animationName.split(",").map((n) => n.trim());
            const durations = s.animationDuration.split(",").map(parseFloat);
            if (names.some((n) => n !== "none") && durations.some((d) => d > 0)) {
                problems.push(el.className + " " + (pseudo ?? "") + " animation " + s.animationName);
            }
            if (s.transitionDuration.split(",").map(parseFloat).some((d) => d > 0.01)) {
                problems.push(el.className + " " + (pseudo ?? "") + " transition " + s.transitionDuration);
            }
        }
    }
    return problems.slice(0, 5);
})()';

test('the login runs no animation with reduced motion', function () {
    $page = visit('/login', ['reducedMotion' => 'reduce']);
    $page->hover('[data-aero=button]');

    expect($page->script(AERO_MOTION_PROBLEMS))->toBe([]);
});

test('the styleguide runs no animation with reduced motion', function () {
    $page = aeroOwnerAt('/styleguide', ['reducedMotion' => 'reduce']);
    $page->hover('#interactive-button');

    expect($page->script(AERO_MOTION_PROBLEMS))->toBe([]);
});

test('no animation on the player controls with reduced motion', function () {
    // aero-player-controls, task 5.1: press "Play" and move both sliders, then read the three new sections.
    $page = aeroOwnerAt('/styleguide', ['reducedMotion' => 'reduce']);
    $page->click('#player-bar-interactive button[aria-label=Play]');
    $page->keys('#slider-volume', ['ArrowRight']);
    $page->keys('#slider-seek', ['ArrowRight']);
    $page->keys('#player-bar-interactive input[aria-label=Seek]', ['ArrowRight']);
    $page->keys('#player-bar-interactive input[aria-label=Volume]', ['ArrowLeft']);

    $problems = $page->script('(() => {
        const problems = [];
        for (const el of document.querySelectorAll("#sliders *, #cover *, #player-bar *")) {
            for (const pseudo of [null, "::before", "::after"]) {
                const s = getComputedStyle(el, pseudo);
                if (s.animationName.split(",").some((n) => n.trim() !== "none")) {
                    problems.push(el.className + " " + (pseudo ?? "") + " animation " + s.animationName);
                }
                if (s.transitionDuration.split(",").map(parseFloat).some((d) => d > 0.01)) {
                    problems.push(el.className + " " + (pseudo ?? "") + " transition " + s.transitionDuration);
                }
            }
        }
        return problems.slice(0, 5);
    })()');

    expect($page->script('document.querySelectorAll("#player-bar-interactive button[aria-label=Pause]").length'))->toBe(1);
    expect($problems)->toBe([]);
});

// --- 5.3 Mobile layout ------------------------------------------------------------

test('the login fits a 375 px phone with 44 px touch areas', function () {
    $page = visit('/login')->resize(375, 667);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

test('the styleguide fits a 375 px phone with 44 px touch areas', function () {
    $page = signedInOwnerVisit('/styleguide')->resize(375, 667);

    expect($page->script(AERO_MOBILE_PROBLEMS))->toBe([]);
});

// --- 5.4 Background image fallback ------------------------------------------------

test('the login stays readable and usable when the background image fails', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    $page = visit('/login');
    // No request blocking in the plugin: point the image token at a missing file instead (design D11).
    $page->script('document.documentElement.style.setProperty("--aero-background-image", "url(/missing-aero-background.svg)")');

    expect($page->script('getComputedStyle(document.querySelector("[data-aero=background]")).backgroundImage'))
        ->toContain('missing-aero-background.svg')
        ->toContain('linear-gradient');

    $page->script('document.documentElement.classList.add("aero-no-blur")');
    expect($page->script(aeroContrastRatio('[data-aero=glass-panel]')))->toBeGreaterThanOrEqual(4.5);

    $page->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->assertPathIs('/dashboard');
});
