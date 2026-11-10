<?php

// Aero input and checkbox (aero-design-system, tasks 3.4): spec aero-design, "Aero text input" and "Aero checkbox".

function aeroTabUntilFocused($page, string $id): void
{
    for ($i = 0; $i < 80; $i++) {
        if ($page->script('document.activeElement?.id') === $id) {
            return;
        }

        $page->keys('#styleguide-title', ['Tab']);
    }

    throw new RuntimeException("Tab never reached #{$id}");
}

test('the text field accepts typing and shows its error linked for assistive technology', function () {
    $page = signedInOwnerVisit('/styleguide')->fill('#styleguide-email', 'owner@example.com');

    expect($page->script('document.querySelector("#styleguide-email").value'))->toBe('owner@example.com');
    expect($page->script('document.querySelector("#styleguide-error").getAttribute("aria-invalid")'))->toBe('true');
    expect($page->script('document.getElementById(document.querySelector("#styleguide-error").getAttribute("aria-describedby")).textContent.trim()'))
        ->toBe('This field is required.');
});

test('the checkbox toggles with the mouse and with Space, and its two states look different', function () {
    $look = '(() => { const s = getComputedStyle(document.querySelector("#cb-unchecked + .aero-checkbox__box")); return s.backgroundImage + "|" + s.backgroundColor; })()';

    $page = signedInOwnerVisit('/styleguide');
    $unchecked = $page->script($look);

    $page->click('Unchecked');
    expect($page->script('document.querySelector("#cb-unchecked").checked'))->toBeTrue();
    $checked = $page->script($look);

    aeroTabUntilFocused($page, 'cb-unchecked');
    $page->keys('#cb-unchecked', ['Space']);

    expect($page->script('document.querySelector("#cb-unchecked").checked'))->toBeFalse();
    expect($page->script($look))->toBe($unchecked);
    expect($checked)->not->toBe($unchecked);
});

test('the checkbox touch area is at least 44 by 44 px and its label toggles it', function () {
    $page = signedInOwnerVisit('/styleguide');

    $size = $page->script('(() => { const r = document.querySelector("#cb-unchecked").closest("[data-aero=checkbox]").getBoundingClientRect(); return [r.width, r.height]; })()');
    expect($size[0])->toBeGreaterThanOrEqual(44);
    expect($size[1])->toBeGreaterThanOrEqual(44);

    $page->click('Unchecked');
    expect($page->script('document.querySelector("#cb-unchecked").checked'))->toBeTrue();
});
