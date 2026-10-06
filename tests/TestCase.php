<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't need built frontend assets.
        $this->withoutVite();

        // A fixed "today" during term 1 keeps date rules deterministic.
        $this->travelTo('2026-09-03 09:00:00');
    }

    //
}
