<?php

// Accessibility and adaptation (aero-design-system, tasks 5.1 to 5.4): spec aero-design, "Visible keyboard focus",
// "Reduced motion", "Mobile layout" and "Background image fallback".

use App\Models\User;

const AERO_CONTROLS = '[...document.querySelectorAll("button, input, a[href], select, textarea")].filter((el) => !el.disabled && el.getClientRects().length > 0)';
const AERO_KEY = '((el) => el.id || el.dataset.sample || el.textContent.trim())';
const AERO_INDICATOR = '((el) => el.classList.contains("aero-checkbox__input") ? el.nextElementSibling : el)';

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

/**
 * Walks the page with Tab from the top and checks every enabled control is reached and shows a 2 px outline
 * that it does not have unfocused.
 */
function aeroAssertFocusWalk($page, string $startSelector): void
{
    // Start the walk from the page title: blur() alone keeps the browser's focus starting point (autofocus on login).
    $page->script('(() => { const start = document.querySelector("'.$startSelector.'"); start.tabIndex = -1; start.focus(); })()');

    $controls = $page->script('(() => {
        const key = '.AERO_KEY.';
        const indicator = '.AERO_INDICATOR.';
        return '.AERO_CONTROLS.'.map((el) => {
            const s = getComputedStyle(indicator(el));
            return { key: key(el), outline: s.outlineStyle === "none" ? 0 : parseFloat(s.outlineWidth) };
        });
    })()');

    expect($controls)->not->toBeEmpty();

    foreach ($controls as $control) {
        expect((float) $control['outline'])->toBe(0.0, "{$control['key']} has an outline without focus");
    }

    $page->keys('*:focus', ['Tab']);

    $visited = [];
    for ($i = 0; $i < count($controls); $i++) {
        if ($i > 0) {
            $page->keys('*:focus', ['Tab']);
        }

        $visited[] = $page->script('(() => {
            const el = document.activeElement;
            const s = getComputedStyle('.AERO_INDICATOR.'(el));
            return { id: el.id, key: '.AERO_KEY.'(el), outline: s.outlineStyle === "none" ? 0 : parseFloat(s.outlineWidth) };
        })()');
    }

    expect(collect($visited)->pluck('key')->sort()->values()->all())
        ->toBe(collect($controls)->pluck('key')->sort()->values()->all());

    foreach ($visited as $control) {
        expect((float) $control['outline'])->toBeGreaterThanOrEqual(2.0, "{$control['key']} shows no 2 px focus outline");
    }
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

// --- 5.3 Mobile layout ------------------------------------------------------------

const AERO_MOBILE_PROBLEMS = '(() => {
    const problems = [];
    if (document.documentElement.scrollWidth > 375) {
        problems.push("scrollWidth " + document.documentElement.scrollWidth);
    }
    for (const el of '.AERO_CONTROLS.') {
        const target = el.classList.contains("aero-checkbox__input") ? el.closest("[data-aero=checkbox]") : el;
        const r = target.getBoundingClientRect();
        const name = el.id || el.dataset.sample || el.textContent.trim();
        if (r.width === 0 || r.height === 0 || r.left < 0 || r.right > 375) {
            problems.push(name + " outside the viewport (" + r.left + "-" + r.right + ")");
        }
        if (r.width < 44 || r.height < 44) {
            problems.push(name + " touch area " + r.width + "x" + r.height);
        }
    }
    return problems;
})()';

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
