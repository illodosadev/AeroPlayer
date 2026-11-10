<?php

// App branding (aero-design-system, task 6.1): spec aero-design, "App branding".

test('the signed-in screens show AeroPlayer and no starter-kit links or name', function () {
    $page = signedInOwnerVisit('/dashboard')
        ->assertSee('AeroPlayer')
        ->assertDontSee('Laravel Starter Kit')
        ->assertDontSee('Github Repo')
        ->assertDontSee('Documentation');

    expect($page->script('[...document.querySelectorAll("a[href]")].map((a) => a.href).filter((h) => h.includes("github.com/laravel/vue-starter-kit") || h.includes("laravel.com/docs/starter-kits"))'))
        ->toBe([]);
});
