<?php

// Aero player bar (aero-player-controls, task 4.4): spec aero-design, "Aero player bar controls", "Player bar play and
// pause", "Player bar time display", "Player bar text layout", "Player bar disabled controls", "Player bar notice area"
// and "Player bar extra controls". Bar 1 (#player-bar-interactive) is interactive; bar 2 (#player-bar-disabled) has the
// disabled controls, the notice and the extra toggle (design D8).

const BAR1 = '#player-bar-interactive';
const BAR2 = '#player-bar-disabled';

/**
 * Walks every enabled control of the page with Tab (never past the last one) and returns, for each stop, its id, its
 * accessible name and the bar it belongs to.
 */
function aeroBarTabWalk($page): array
{
    $page->script('(() => { const start = document.querySelector("#styleguide-title"); start.tabIndex = -1; start.focus(); })()');
    $stops = $page->script('[...document.querySelectorAll("button, input, a[href]")].filter((el) => !el.disabled && el.getClientRects().length > 0).length');

    $visited = [];
    for ($i = 0; $i < $stops; $i++) {
        $page->keys('*:focus', ['Tab']);
        $visited[] = $page->script('(() => {
            const el = document.activeElement;
            const bar = el.closest("#player-bar-interactive, #player-bar-disabled");
            return { id: el.id, name: el.getAttribute("aria-label") || el.textContent.trim(), bar: bar ? bar.id : null };
        })()');
    }

    return $visited;
}

test('the bar has the basic controls', function () {
    $page = signedInOwnerVisit('/styleguide');

    $bar = $page->script('(() => {
        const root = document.querySelector("'.BAR1.' [data-aero=player-bar]");
        const named = (name) => root.querySelector("[aria-label=\'" + name + "\']");
        return {
            panel: !!root.closest("[data-aero=glass-panel]"),
            cover: root.querySelectorAll("[data-aero=cover]").length,
            title: root.querySelector("[data-part=title]").textContent.trim(),
            artist: root.querySelector("[data-part=artist]").textContent.trim(),
            controls: ["Previous", "Play", "Next", "Seek", "Volume"].filter((name) => !!named(name)),
            orbs: ["Previous", "Play", "Next"].map((name) => named(name).dataset.aero + ":" + named(name).dataset.shape),
            widths: ["Previous", "Play", "Next"].map((name) => named(name).getBoundingClientRect().width),
        };
    })()');

    expect($bar['panel'])->toBeTrue();
    expect($bar['cover'])->toBe(1);
    expect($bar['title'])->toBe('Song A');
    expect($bar['artist'])->toBe('Artist A');
    expect($bar['controls'])->toBe(['Previous', 'Play', 'Next', 'Seek', 'Volume']);
    expect($bar['orbs'])->toBe(['button:orb', 'button:orb', 'button:orb']);
    [$previous, $play, $next] = $bar['widths'];
    expect($play)->toBeGreaterThan($previous);
    expect($play)->toBeGreaterThan($next);
});

test('play and pause change the control without sound', function () {
    $page = signedInOwnerVisit('/styleguide');

    $page->click(BAR1.' button[aria-label=Play]');

    expect($page->script('document.querySelectorAll("'.BAR1.' button[aria-label=Pause] [data-aero=icon][data-icon=pause]").length'))->toBe(1);
    expect($page->script('document.querySelectorAll("'.BAR1.' button[aria-label=Play]").length'))->toBe(0);
    expect($page->script('document.querySelectorAll("audio, video").length'))->toBe(0);

    $page->click(BAR1.' button[aria-label=Pause]');

    expect($page->script('document.querySelectorAll("'.BAR1.' button[aria-label=Play] [data-aero=icon][data-icon=play]").length'))->toBe(1);
    expect($page->script('document.querySelectorAll("audio, video").length'))->toBe(0);
});

test('the bar shows the times', function () {
    $page = signedInOwnerVisit('/styleguide');

    expect($page->text(BAR1.' [data-time=elapsed]'))->toBe('0:10');
    expect($page->text(BAR1.' [data-time=total]'))->toBe('3:00');
    expect($page->attribute(BAR1.' input[aria-label=Seek]', 'aria-valuetext'))->toBe('0:10 of 3:00');
});

test('seeking updates the elapsed time', function () {
    $page = signedInOwnerVisit('/styleguide');

    $page->keys(BAR1.' input[aria-label=Seek]', ['ArrowRight']);

    expect($page->text(BAR1.' [data-time=elapsed]'))->toBe('0:11');
    expect($page->attribute(BAR1.' input[aria-label=Seek]', 'aria-valuetext'))->toBe('0:11 of 3:00');
});

test('a song of an hour or more', function () {
    $page = signedInOwnerVisit('/styleguide');

    $page->click('#bar-long-song');

    expect($page->text(BAR1.' [data-time=elapsed]'))->toBe('0:00');
    expect($page->text(BAR1.' [data-time=total]'))->toBe('1:02:05');
});

test('empty title and artist do not move the bar', function () {
    $page = signedInOwnerVisit('/styleguide');
    $height = 'document.querySelector("'.BAR1.' [data-aero=player-bar]").getBoundingClientRect().height';

    $before = $page->script($height);
    $page->click('#bar-toggle-text');

    expect($page->script($height))->toBe($before);
    expect($page->script('document.querySelector("'.BAR1.' [data-part=title]").textContent'))->toBe('');
    expect($page->script('document.querySelector("'.BAR1.' [data-part=artist]").textContent'))->toBe('');
    expect($page->script('getComputedStyle(document.querySelector("'.BAR1.' [data-part=title]"), "::before").content'))->toBe('none');
});

test('a long title on a phone', function () {
    $page = signedInOwnerVisit('/styleguide')->resize(375, 667);
    $page->click('#bar-long-title');

    $result = $page->script('(() => {
        const problems = [];
        if (document.documentElement.scrollWidth > 375) {
            problems.push("scrollWidth " + document.documentElement.scrollWidth);
        }
        for (const bar of document.querySelectorAll("[data-aero=player-bar]")) {
            for (const el of bar.querySelectorAll("button, input, a[href]")) {
                const r = el.getBoundingClientRect();
                const name = el.getAttribute("aria-label") || el.textContent.trim();
                if (r.width === 0 || r.height === 0 || r.left < 0 || r.right > 375) {
                    problems.push(name + " outside the viewport (" + r.left + "-" + r.right + ")");
                }
                if (!el.disabled && (r.width < 44 || r.height < 44)) {
                    problems.push(name + " touch area " + r.width + "x" + r.height);
                }
            }
        }
        const title = document.querySelector("'.BAR1.' [data-part=title]");
        const s = getComputedStyle(title);
        return {
            problems,
            overflow: s.textOverflow,
            whiteSpace: s.whiteSpace,
            lines: Math.round(title.getBoundingClientRect().height / parseFloat(s.lineHeight)),
            truncated: title.scrollWidth > title.clientWidth,
            text: title.textContent,
            attribute: title.getAttribute("title"),
        };
    })()');

    expect($result['problems'])->toBe([]);
    expect(mb_strlen($result['text']))->toBe(120);
    expect($result['overflow'])->toBe('ellipsis');
    expect($result['whiteSpace'])->toBe('nowrap');
    expect($result['lines'])->toBe(1);
    expect($result['truncated'])->toBeTrue();
    expect($result['attribute'])->toBe($result['text']);
});

test('disabled controls cannot be used', function () {
    $page = signedInOwnerVisit('/styleguide');
    $controls = '["Previous", "Play", "Next", "Seek"].map((name) => document.querySelector("'.BAR2.' [aria-label=" + name + "]"))';

    expect($page->script($controls.'.map((el) => el.disabled)'))->toBe([true, true, true, true]);

    // A disabled button dispatches no click, so the listener never runs.
    $clicks = $page->script('(() => {
        let count = 0;
        for (const el of '.$controls.') {
            el.addEventListener("click", () => count++);
            el.click();
        }
        return count;
    })()');
    expect($clicks)->toBe(0);

    $visited = aeroBarTabWalk($page);
    expect(collect($visited)->where('bar', 'player-bar-disabled')->pluck('name')->intersect(['Previous', 'Play', 'Next', 'Seek'])->all())->toBe([]);

    $page->drag(BAR2.' input[aria-label=Seek]', BAR2.' [data-time=total]');
    expect($page->script('document.querySelector("'.BAR2.' input[aria-label=Seek]").value'))->toBe('20');
});

test('a notice with a button and a link', function () {
    $page = signedInOwnerVisit('/styleguide');

    $notice = $page->script('(() => {
        const bar = document.querySelector("'.BAR2.' [data-aero=player-bar]");
        const status = bar.querySelector("[role=status]");
        const button = document.querySelector("#notice-play-here");
        const link = document.querySelector("#notice-reconnect");
        return {
            message: status.textContent.trim(),
            live: status.getAttribute("aria-live"),
            buttonText: button.textContent.trim(),
            buttonAero: button.dataset.aero,
            linkText: link.textContent.trim(),
            inBar: bar.contains(button) && bar.contains(link),
            inStatus: status.contains(button) || status.contains(link),
        };
    })()');

    expect($notice['message'])->toBe('Playing on Kitchen Speaker');
    expect($notice['live'])->toBe('polite');
    expect($notice['buttonText'])->toBe('Play here');
    expect($notice['buttonAero'])->toBe('button');
    expect($notice['linkText'])->toBe('Reconnect Spotify');
    expect($notice['inBar'])->toBeTrue();
    expect($notice['inStatus'])->toBeFalse();

    $ids = collect(aeroBarTabWalk($page))->pluck('id');
    expect($ids)->toContain('notice-play-here');
    expect($ids)->toContain('notice-reconnect');
});

test('an extra toggle button in the bar', function () {
    $page = signedInOwnerVisit('/styleguide');

    expect($page->script('!!document.querySelector("#bar-extra").closest("'.BAR2.' [data-aero=glass-panel]")'))->toBeTrue();
    expect($page->attribute('#bar-extra', 'aria-pressed'))->toBe('false');

    $page->click('#bar-extra');

    expect($page->attribute('#bar-extra', 'aria-pressed'))->toBe('true');
    $size = $page->script('(() => { const r = document.querySelector("#bar-extra").getBoundingClientRect(); return [r.width, r.height]; })()');
    expect($size[0])->toBeGreaterThanOrEqual(44);
    expect($size[1])->toBeGreaterThanOrEqual(44);
});
