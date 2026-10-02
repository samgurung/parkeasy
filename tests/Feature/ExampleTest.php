<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The root is the front door. A guest gets the landing page: it has to explain the app and
     * offer the way in, because "redirect to /login" tells a first-time visitor what they may not
     * see but not what they are signing in to.
     */
    public function test_the_root_is_a_landing_page_for_a_guest(): void
    {
        $response = $this->get('/')->assertOk();

        // An explanation, and the way in.
        $response->assertSee('Smart parking for a smart city');
        $response->assertSee('Sign in');
        $response->assertSee(route('login'), false);

        // And it must not be the terminal in disguise: no gate, no card entry, no binding.
        $response->assertDontSee('Choose your gate');
        $response->assertDontSee('id="manual-card"', false);
    }
}
