<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoFoundationTest extends TestCase
{
    public function test_homepage_has_primary_search_metadata_and_structured_data(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('Commercial Cleaning Services Klang Valley | CuciNow.co')
            ->assertSee('application/ld+json', false)
            ->assertSee('LocalBusiness')
            ->assertSee('FAQPage')
            ->assertSee('Commercial cleaning for offices, halls &amp; large spaces.', false)
            ->assertDontSee('Thursina Land &amp; Services', false);
    }

    public function test_each_configured_service_has_an_indexable_page(): void
    {
        foreach (config('cleaning-services') as $slug => $service) {
            $this->get(route('services.show', $slug))
                ->assertOk()
                ->assertSee($service['name'])
                ->assertSee('<link rel="canonical" href="'.route('services.show', $slug).'">', false)
                ->assertSee('"@type":"Service"', false)
                ->assertSee('BreadcrumbList');
        }
    }

    public function test_sitemap_contains_homepage_and_every_service_page(): void
    {
        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.route('home').'</loc>', false);

        foreach (array_keys(config('cleaning-services')) as $slug) {
            $response->assertSee('<loc>'.route('services.show', $slug).'</loc>', false);
        }
    }

    public function test_unknown_service_returns_not_found(): void
    {
        $this->get('/services/home-cleaning')->assertNotFound();
    }
}
