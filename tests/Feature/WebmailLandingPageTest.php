<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebmailLandingPageTest extends TestCase
{
    public function test_webmail_landing_page_links_to_the_configured_secure_login(): void
    {
        config(['webmail.login_url' => 'https://mail-host.example.test:2096']);

        $this->get(route('webmail'))
            ->assertOk()
            ->assertSee('CuciNow business mail')
            ->assertSee('https://mail-host.example.test:2096', false)
            ->assertSee('mail-host.example.test')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('<form', false);
    }

    public function test_webmail_landing_page_rejects_an_insecure_destination(): void
    {
        config(['webmail.login_url' => 'http://mail-host.example.test:2095']);

        $this->get(route('webmail'))->assertStatus(503);
    }

    public function test_webmail_landing_page_rejects_an_invalid_destination(): void
    {
        config(['webmail.login_url' => 'not-a-url']);

        $this->get(route('webmail'))->assertStatus(503);
    }
}
