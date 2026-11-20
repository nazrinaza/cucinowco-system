<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLegalPagesTest extends TestCase
{
    public function test_each_legal_document_has_its_own_public_page(): void
    {
        $pages = [
            'legal.privacy' => 'Privacy Policy',
            'legal.terms' => 'Terms of Use',
            'legal.service-policy' => 'Service Policy',
        ];

        foreach ($pages as $route => $heading) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('20 November 2026')
                ->assertSee('CuciNow.co by Thursina');
        }
    }

    public function test_public_footer_links_to_all_legal_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.service-policy'), false);
    }
}
