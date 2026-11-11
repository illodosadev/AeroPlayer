<?php

// Asset provenance (aero-design-system, design D7): every visual asset has a row in CREDITS.md.

function aeroAssetFiles(): array
{
    $base = resource_path('aero');
    $files = [];

    foreach (['backgrounds', 'icons', 'fonts', 'samples'] as $folder) {
        $dir = $base.DIRECTORY_SEPARATOR.$folder;

        if (! is_dir($dir)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($base) + 1));
            }
        }
    }

    sort($files);

    return $files;
}

test('every Aero asset has a provenance row with its origin and an ISO date', function () {
    $credits = file_get_contents(resource_path('aero/CREDITS.md'));
    $files = aeroAssetFiles();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $row = collect(explode("\n", $credits))
            ->first(fn (string $line) => str_starts_with($line, '| `'.$file.'` |'));

        expect($row)->not->toBeNull("No provenance row for {$file} in resources/aero/CREDITS.md");

        $cells = array_map('trim', explode('|', trim($row, " |\t")));
        expect($cells[1] ?? '')->not->toBe('', "No origin for {$file}");
        expect($cells[2] ?? '')->toMatch('/^\d{4}-\d{2}-\d{2}$/', "No ISO date for {$file}");
    }
});
