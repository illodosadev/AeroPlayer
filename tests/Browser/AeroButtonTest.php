<?php

// Glossy button (aero-design-system, tasks 3.3): spec aero-design, "Glossy button states" and
// "Disabled button cannot be used". "Held down" cannot be automated with this plugin (design D11, manual check).

test('the four states of the rectangle and the orb look different from each other', function () {
    $samples = signedInOwnerVisit('/styleguide')->script('[...document.querySelectorAll("[data-sample]")].map((el) => {
        const s = getComputedStyle(el);
        return { sample: el.dataset.sample, look: s.backgroundImage + "|" + s.boxShadow };
    })');

    expect($samples)->toHaveCount(8);

    foreach (['rect', 'orb'] as $shape) {
        $looks = collect($samples)
            ->filter(fn (array $sample) => str_starts_with($sample['sample'], $shape.'-'))
            ->pluck('look');

        expect($looks)->toHaveCount(4);
        expect($looks->unique())->toHaveCount(4);
    }
});

test('the pointer over the interactive button changes its look', function () {
    $page = signedInOwnerVisit('/styleguide');
    $normal = $page->script('getComputedStyle(document.querySelector("#interactive-button")).backgroundImage');

    $page->hover('#interactive-button');

    expect($page->script('document.querySelector("#interactive-button").matches(":hover")'))->toBeTrue();
    expect($page->script('getComputedStyle(document.querySelector("#interactive-button")).backgroundImage'))->not->toBe($normal);
});

test('releasing the mouse outside the button does not leave it pressed', function () {
    $page = signedInOwnerVisit('/styleguide');
    $normal = $page->script('getComputedStyle(document.querySelector("#interactive-button")).backgroundImage');

    $page->drag('#interactive-button', '#buttons-heading');

    expect($page->script('document.querySelector("#interactive-button").matches(":active")'))->toBeFalse();
    expect($page->script('getComputedStyle(document.querySelector("#interactive-button")).backgroundImage'))->toBe($normal);
});

test('the enabled button responds to a click', function () {
    signedInOwnerVisit('/styleguide')
        ->assertSee('Clicks: 0')
        ->press('Press me')
        ->assertSee('Clicks: 1');
});

test('the disabled button has the disabled attribute and ignores clicks', function () {
    $page = signedInOwnerVisit('/styleguide')->assertSee('Disabled clicks: 0');

    expect($page->script('document.querySelector("#disabled-button").disabled'))->toBeTrue();

    $page->script('document.querySelector("#disabled-button").click()');

    $page->assertSee('Disabled clicks: 0');
});

test('keyboard focus never reaches the disabled button', function () {
    $page = signedInOwnerVisit('/styleguide');

    $focused = [];
    for ($i = 0; $i < 60; $i++) {
        $page->keys('#styleguide-title', ['Tab']);
        $focused[] = $page->script('document.activeElement?.id ?? ""');
    }

    expect($focused)->toContain('interactive-button');
    expect($focused)->not->toContain('disabled-button');
});
