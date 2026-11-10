<?php

// Glass panel (aero-design-system, tasks 3.2): spec aero-design, "Glass panel lets the background through",
// "No-blur fallback" and the theme-token scenario.

test('the glass panel lets the background through', function () {
    $page = signedInOwnerVisit('/styleguide');

    $panel = $page->script('(() => {
        const s = getComputedStyle(document.querySelector("[data-aero=glass-panel]"));
        return { backdrop: s.backdropFilter, alpha: parseFloat((s.backgroundColor.split(",")[3] ?? "1").replace(")", "")) };
    })()');

    expect($panel['backdrop'])->not->toBe('none');
    expect($panel['alpha'])->toBeLessThan(1);
});

test('the no-blur fallback keeps every panel almost opaque and readable', function () {
    $page = signedInOwnerVisit('/styleguide');
    $page->script('document.documentElement.classList.add("aero-no-blur")');

    $panels = $page->script('[...document.querySelectorAll("[data-aero=glass-panel]")].map((p) => {
        const s = getComputedStyle(p);
        return { backdrop: s.backdropFilter, alpha: parseFloat((s.backgroundColor.split(",")[3] ?? "1").replace(")", "")) };
    })');

    expect($panels)->not->toBeEmpty();

    foreach ($panels as $panel) {
        expect($panel['backdrop'])->toBe('none');
        expect($panel['alpha'])->toBeGreaterThanOrEqual(0.85);
    }

    expect($page->script(aeroContrastRatio('[data-aero=glass-panel]')))->toBeGreaterThanOrEqual(4.5);
});

test('a theme token reaches the glass panel', function () {
    $page = signedInOwnerVisit('/styleguide');
    $page->script('document.documentElement.style.setProperty("--aero-panel-bg", "rgb(255, 0, 0)")');

    $background = $page->script('getComputedStyle(document.querySelector("[data-aero=glass-panel]")).backgroundColor');

    expect($background)->toBe('rgb(255, 0, 0)');
});
