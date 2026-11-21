<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebmailLandingPageTest extends TestCase
{
    public function test_webmail_landing_page_posts_credentials_directly_to_cpanel(): void
    {
        config(['webmail.login_url' => 'https://mail-host.example.test:2096']);
        config(['webmail.destination_path' => '/3rdparty/roundcube/index.php']);

        $this->get(route('webmail'))
            ->assertOk()
            ->assertSee('CuciNow business mail')
            ->assertSee('action="https://mail-host.example.test:2096/login/"', false)
            ->assertSee('mail-host.example.test')
            ->assertSee('method="post"', false)
            ->assertSee('name="user"', false)
            ->assertSee('name="pass"', false)
            ->assertSee('name="goto_uri" value="/3rdparty/roundcube/index.php"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'none'; form-action https://mail-host.example.test:2096; frame-ancestors 'none'; img-src 'self' data:; style-src 'self'; script-src 'none'; connect-src 'none'; object-src 'none'");
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

    public function test_webmail_landing_page_rejects_an_external_destination_path(): void
    {
        config(['webmail.destination_path' => '//malicious.example.test/login']);

        $this->get(route('webmail'))->assertStatus(503);
    }
}
