<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia pages render through @vite; tests don't need a built frontend (design D4).
        $this->withoutVite();

        // The kit keeps its pages in resources/js/pages (lowercase). Inertia's test helper
        // defaults to Pages, which only resolves on case-insensitive filesystems.
        config(['inertia.testing.page_paths' => [resource_path('js/pages')]]);
    }
}
