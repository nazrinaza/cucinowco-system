<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('A cleaner space')
            ->assertSee('CuciNow.co')
            ->assertSee('Book my free site visit')
            ->assertSee('data-accordion', false)
            ->assertSee('name="cucinow-faq"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('images/og-cucinow.png')
            ->assertSee('name="twitter:image"', false)
            ->assertSee('images/favicon-32x32.png');
    }
}
