<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class BrowserTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Browser tests load the real build of the frontend, so withoutVite() is not called here.
        // Same lowercase page path as TestCase (design D11).
        config(['inertia.testing.page_paths' => [resource_path('js/pages')]]);
    }
}
