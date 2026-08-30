<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Smoke test that the application boots and serves a route.
     *
     * This previously asserted that `/` returned 200, which was true of the
     * Laravel welcome page. `/` is now the dashboard and requires
     * authentication, so that assertion no longer describes intended
     * behaviour. The redirect is asserted directly in
     * Tests\Feature\Auth\LoginTest::test_guests_are_redirected_from_the_dashboard.
     */
    public function test_the_application_boots(): void
    {
        $this->get('/login')->assertOk();
    }
}
