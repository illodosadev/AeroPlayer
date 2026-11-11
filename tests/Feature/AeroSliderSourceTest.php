<?php

// The slider's range pseudo-elements never animate (aero-player-controls, design D9, task 5.1): spec aero-design,
// "Reduced motion". The global reduced-motion rule (`*`, `::before`, `::after`) does not reach them, and the browser
// cannot read their computed style, so the source is checked instead.

test('the slider declares no transition or animation on its range pseudo-elements', function () {
    $source = file_get_contents(resource_path('js/components/aero/AeroSlider.vue'));

    preg_match('/<style[^>]*>(.*?)<\/style>/s', $source, $style);
    $css = preg_replace('/\/\*.*?\*\//s', '', $style[1] ?? '');

    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
    $pseudoRules = array_filter($rules, fn (array $rule) => preg_match('/::-(webkit-slider|moz-range)/', $rule[1]));

    expect(count($pseudoRules))->toBeGreaterThanOrEqual(4);

    foreach ($pseudoRules as [, $selector, $body]) {
        expect($body)->not->toMatch('/\b(transition|animation)[a-z-]*\s*:/', 'Motion on '.trim($selector));
    }
});
