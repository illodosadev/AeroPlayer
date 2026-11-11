<?php

// Aero cover with fallback (aero-player-controls, task 3.1): spec aero-design, "Aero cover with fallback".
// The broken sample points at a path the app answers with 404 (design D7).

/** Waits until every cover image on the page has finished loading or failing. */
function aeroCoversSettled($page)
{
    $page->script('new Promise((resolve) => {
        const check = () => {
            const pending = [...document.querySelectorAll("[data-aero=cover]")].some((c) => c.dataset.state === "loading");
            pending ? setTimeout(check, 50) : resolve(true);
        };
        check();
    })');

    return $page;
}

test('a song without a cover shows the hidden fallback with the note icon', function () {
    $page = aeroCoversSettled(signedInOwnerVisit('/styleguide'));

    expect($page->script('document.querySelectorAll("#cover-no-image [data-aero=cover-fallback]").length'))->toBe(1);
    expect($page->script('document.querySelectorAll("#cover-no-image img").length'))->toBe(0);
    expect($page->attribute('#cover-no-image [data-aero=cover-fallback]', 'aria-hidden'))->toBe('true');
    expect($page->script('document.querySelectorAll("#cover-no-image [data-aero=cover-fallback] [data-aero=icon][data-icon=note] svg").length'))->toBe(1);
});

test('a cover image that fails to load shows the fallback and no broken image', function () {
    $page = aeroCoversSettled(signedInOwnerVisit('/styleguide'));

    expect($page->script('document.querySelectorAll("#cover-broken [data-aero=cover-fallback]").length'))->toBe(1);
    expect($page->script('[...document.querySelectorAll("#cover-broken img")].filter((img) => img.naturalWidth === 0).length'))->toBe(0);
});

test('a cover with an image shows it with a text alternative and no fallback', function () {
    $page = aeroCoversSettled(signedInOwnerVisit('/styleguide'));

    expect($page->script('(() => { const img = document.querySelector("#cover-with-image img"); return !!img && img.complete && img.naturalWidth > 0; })()'))->toBeTrue();
    expect($page->script('document.querySelector("#cover-with-image img").getAttribute("src").startsWith("data:")'))->toBeFalse();
    expect(trim((string) $page->attribute('#cover-with-image img', 'alt')))->not->toBe('');
    expect($page->script('document.querySelectorAll("#cover-with-image [data-aero=cover-fallback]").length'))->toBe(0);
});

test('every cover sample keeps the same size', function () {
    $page = aeroCoversSettled(signedInOwnerVisit('/styleguide'));

    $sizes = $page->script('["#cover-with-image", "#cover-no-image", "#cover-broken"].map((id) => {
        const r = document.querySelector(id + " [data-aero=cover]").getBoundingClientRect();
        return r.width + "x" + r.height;
    })');

    expect($sizes)->toHaveCount(3);
    expect(array_unique($sizes))->toHaveCount(1);
    expect($sizes[0])->toBe('96x96');
});
