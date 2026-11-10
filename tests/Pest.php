<?php

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

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\BrowserTestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
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
    \App\Models\User::factory()->create(['email' => 'owner@example.com', 'password' => 'Correct-Horse-42']);

    return visit('/login')
        ->fill('email', 'owner@example.com')
        ->fill('password', 'Correct-Horse-42')
        ->press('Log in')
        ->wait(2)
        ->navigate($path);
}

/**
 * JavaScript expression that returns the WCAG contrast ratio between the text and the background
 * colour of the first element matching $selector. Translucent backgrounds are treated as opaque (design D11).
 */
function aeroContrastRatio(string $selector): string
{
    return "(() => {
        const parse = (c) => c.match(/[\d.]+/g).map(Number);
        const lum = ([r, g, b]) => {
            const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
            return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };
        const el = document.querySelector('{$selector}');
        const style = getComputedStyle(el);
        const a = lum(parse(style.color));
        const b = lum(parse(style.backgroundColor));
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    })()";
}
