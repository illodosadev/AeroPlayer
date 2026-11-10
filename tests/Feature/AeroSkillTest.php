<?php

// The Aero style skill (aero-design-system, task 7.1): spec aero-design, "Aero style skill".

function aeroSkillText(): string
{
    $dir = base_path('.claude/skills/frutiger-aero');
    $text = '';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'md') {
            $text .= file_get_contents($file->getPathname())."\n";
        }
    }

    return $text;
}

/** @return list<string> */
function aeroDeclaredTokens(string $dir, string $extension): array
{
    $tokens = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            preg_match_all('/(--aero-[a-z0-9-]+)\s*:/', file_get_contents($file->getPathname()), $matches);
            array_push($tokens, ...$matches[1]);
        }
    }

    $tokens = array_values(array_unique($tokens));
    sort($tokens);

    return $tokens;
}

test('the skill file exists with a name and a description about the interface', function () {
    $skill = file_get_contents(base_path('.claude/skills/frutiger-aero/SKILL.md'));

    expect($skill)->toMatch('/^---\R/');
    preg_match('/^---\R(.*?)\R---/s', $skill, $frontmatter);

    expect($frontmatter[1] ?? '')->toMatch('/^name: frutiger-aero$/m');
    expect($frontmatter[1] ?? '')->toMatch('/^description: .*(create|change).*interface/mi');
});

test('the skill covers the four traits, the components with their files and the accessibility rules', function () {
    $text = aeroSkillText();

    foreach (['Glossy and glass', 'Nature and sky', 'Classic media players', 'Skeuomorphism'] as $trait) {
        expect($text)->toMatch('/^#+ '.preg_quote($trait, '/').'$/m');
    }

    foreach (['AeroButton', 'AeroGlassPanel', 'AeroInput', 'AeroCheckbox', 'AeroBackground'] as $component) {
        $path = "resources/js/components/aero/{$component}.vue";
        expect($text)->toContain($path);
        expect(base_path($path))->toBeFile();
    }

    foreach (['focus', 'reduced motion', 'no-blur fallback', '4.5:1', '44x44'] as $rule) {
        expect(strtolower($text))->toContain(strtolower($rule));
    }
});

test('the skill lists exactly the declared tokens and components declare none', function () {
    $declared = aeroDeclaredTokens(resource_path('css/aero'), 'css');

    preg_match_all('/--aero-[a-z0-9-]+/', aeroSkillText(), $matches);
    $named = array_values(array_unique($matches[0]));
    sort($named);

    expect($declared)->not->toBeEmpty();
    expect($named)->toBe($declared);
    expect(aeroDeclaredTokens(resource_path('js/components/aero'), 'vue'))->toBe([]);
});
