<?php

// Aero slider (aero-player-controls, task 2.2): spec aero-design, "Aero slider".
// The plugin's click() always hits the centre, so pointer input uses drag() to the value label right of the track
// (design D9).

function aeroSliderValue($page, string $id): string
{
    return $page->script("document.querySelector('#{$id}').value");
}

test('the keyboard moves the slider one step', function () {
    $page = signedInOwnerVisit('/styleguide');

    expect(aeroSliderValue($page, 'slider-volume'))->toBe('50');

    $page->keys('#slider-volume', ['ArrowRight']);

    expect($page->script('document.activeElement.id'))->toBe('slider-volume');
    expect(aeroSliderValue($page, 'slider-volume'))->toBe('51');
    expect($page->text('#slider-volume-value'))->toBe('51');
});

test('the pointer moves the slider and stops at the end', function () {
    $page = signedInOwnerVisit('/styleguide');

    expect(aeroSliderValue($page, 'slider-seek'))->toBe('30');

    $page->drag('#slider-seek', '#slider-seek-value');

    expect(aeroSliderValue($page, 'slider-seek'))->toBe('100');
    expect($page->text('#slider-seek-value'))->toBe('100');
});

test('the slider stays within its limits', function () {
    $page = signedInOwnerVisit('/styleguide');

    $page->keys('#slider-volume', ['End']);
    expect(aeroSliderValue($page, 'slider-volume'))->toBe('100');

    $page->keys('#slider-volume', ['ArrowRight']);
    expect(aeroSliderValue($page, 'slider-volume'))->toBe('100');

    $page->keys('#slider-volume', ['End']);
    expect(aeroSliderValue($page, 'slider-volume'))->toBe('100');

    $page->keys('#slider-volume', ['Home']);
    expect(aeroSliderValue($page, 'slider-volume'))->toBe('0');
    expect($page->text('#slider-volume-value'))->toBe('0');
});

test('a disabled slider ignores input', function () {
    $page = signedInOwnerVisit('/styleguide');

    expect($page->script("document.querySelector('#slider-disabled').disabled"))->toBeTrue();

    // Walk every enabled control with Tab: the disabled slider is never focused.
    $page->script('(() => { const start = document.querySelector("#styleguide-title"); start.tabIndex = -1; start.focus(); })()');
    $stops = $page->script('[...document.querySelectorAll("button, input, a[href]")].filter((el) => !el.disabled).length');

    $visited = [];
    for ($i = 0; $i < $stops; $i++) {
        $page->keys('*:focus', ['Tab']);
        $visited[] = $page->script('document.activeElement.id');
    }

    expect($visited)->toContain('slider-volume');
    expect($visited)->not->toContain('slider-disabled');

    $page->drag('#slider-disabled', '#slider-disabled-value');
    expect(aeroSliderValue($page, 'slider-disabled'))->toBe('40');
});
