<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_tag_is_not_rendered_without_an_id(): void
    {
        config(['services.google.tag_id' => null]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('googletagmanager.com/gtag/js', false);
    }

    public function test_google_tag_is_rendered_on_public_and_admin_pages(): void
    {
        config(['services.google.tag_id' => 'G-CUCINOWTEST']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-CUCINOWTEST', false)
            ->assertSee("'site_area': 'public'", false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee("'site_area': 'admin'", false);

        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-CUCINOWTEST', false)
            ->assertSee("'site_area': 'admin'", false);
    }
}
