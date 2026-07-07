<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/branding/settings.json'));

        parent::tearDown();
    }

    public function test_branding_page_is_displayed_with_shared_branding_props()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('branding.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Branding')
                ->where('branding.name', config('app.name'))
                ->where('name', config('app.name'))
                ->has('branding.logo_url')
                ->has('branding.favicon_url'),
            );
    }

    public function test_branding_assets_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('branding.edit'))
            ->patch(route('branding.update'), [
                'name' => 'Pusdata Gegana',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
                'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('branding.edit'));

        $settings = json_decode((string) File::get(storage_path('app/branding/settings.json')), true);

        $this->assertSame('Pusdata Gegana', $settings['name'] ?? null);
        $this->assertSame('branding/lgo.png', $settings['logo_path'] ?? null);
        $this->assertSame('branding/gegana-fav.png', $settings['favicon_path'] ?? null);
        $this->assertFileExists(public_path('branding/lgo.png'));
        $this->assertFileExists(public_path('branding/gegana-fav.png'));
    }
}
