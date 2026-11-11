<?php

// The styleguide sections (aero-player-controls, design D9): spec aero-design, "Styleguide page",
// scenario "The owner opens the styleguide". The feature test does not render the page, so the headings are read here.

const STYLEGUIDE_SECTIONS = [
    'buttons-heading' => 'Buttons',
    'glass-heading' => 'Glass panel',
    'inputs-heading' => 'Inputs',
    'checkbox-heading' => 'Checkbox',
    'sliders-heading' => 'Sliders',
    'cover-heading' => 'Cover',
    'player-bar-heading' => 'Player bar',
];

test('the styleguide shows every section over the Aero background', function () {
    $page = signedInOwnerVisit('/styleguide');

    foreach (STYLEGUIDE_SECTIONS as $id => $name) {
        expect($page->text("#{$id}"))->toBe($name);
        expect($page->script("!!document.querySelector('#{$id}').closest('[data-aero=background]')"))
            ->toBeTrue("{$name} is not over the Aero background");
    }
});
