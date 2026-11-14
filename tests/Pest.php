<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\BrowserTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(BrowserTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

// Fail browser actions after 10 s instead of the plugin's default, so a wrong selector surfaces quickly.
pest()->browser()->timeout(10000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Signs the owner in through the form and opens the given page in the same browser session.
 * Each visit() starts a new browser context, so the sign-in and the page share one chain (design D11).
 */
function signedInOwnerVisit(string $path)
{
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    return visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path);
}

/**
 * JavaScript expression that returns the WCAG contrast ratio between the text and the background
 * colour of the first element matching $selector, or of $backgroundSelector when given. Translucent backgrounds are
 * treated as opaque (design D11).
 */
function aeroContrastRatio(string $selector, ?string $backgroundSelector = null): string
{
    $background = $backgroundSelector ?? $selector;

    return "(() => {
        const parse = (c) => c.match(/[\d.]+/g).map(Number);
        const lum = ([r, g, b]) => {
            const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
            return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };
        const a = lum(parse(getComputedStyle(document.querySelector('{$selector}')).color));
        const b = lum(parse(getComputedStyle(document.querySelector('{$background}')).backgroundColor));
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    })()";
}

/*
| Accessibility checks shared by the Aero pages' browser tests (aero-design "Visible keyboard focus" and
| "Mobile layout"; moved here from AeroAccessibilityTest by spotify-connection, design D12).
*/

// The enabled, rendered controls; disabled ones never take focus, so a Tab walk must not count them.
const AERO_CONTROLS = '[...document.querySelectorAll("button, input, a[href], select, textarea")].filter((el) => !el.disabled && el.getClientRects().length > 0)';
const AERO_KEY = '((el) => el.id || el.dataset.sample || el.textContent.trim())';
const AERO_INDICATOR = '((el) => el.classList.contains("aero-checkbox__input") ? el.nextElementSibling : el)';

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

// Returns the 375 px layout problems of the page: horizontal scroll, controls outside the viewport, small touch areas.
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
