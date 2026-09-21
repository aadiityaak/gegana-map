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
        $this->assertFileExists(public_path('branding/pusdata.png'));
        $this->assertFileExists(public_path('branding/gegana-fav.png'));
    }

    public function test_gambar_jpg_dikonversi_otomatis_ke_png()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('branding.edit'))
            ->patch(route('branding.update'), [
                'logo' => UploadedFile::fake()->image('logo-baru.jpg', 300, 120),
                'favicon' => UploadedFile::fake()->image('favicon-baru.jpeg', 800, 800),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('branding.edit'));

        $settings = json_decode((string) File::get(storage_path('app/branding/settings.json')), true);

        $ukuran = [];

        foreach (['logo', 'favicon'] as $kunci) {
            $path = (string) ($settings[$kunci] ?? '');
            $absolut = public_path($path);

            $this->assertStringEndsWith('.png', $path, "Berkas {$kunci} harus disimpan sebagai PNG.");
            $this->assertFileExists($absolut);

            $info = getimagesize($absolut) ?: [];
            $this->assertSame('image/png', $info['mime'] ?? null);
            $ukuran[$kunci] = (int) ($info[0] ?? 9999);

            File::delete($absolut);
        }

        // JPG 800x800 untuk favicon harus diperkecil otomatis (maks 512 px).
        $this->assertLessThanOrEqual(512, $ukuran['favicon']);
    }

    public function test_gambar_jpg_lama_ikut_muncul_di_daftar_pilihan()
    {
        $user = User::factory()->create();
        $folder = public_path('branding');
        File::ensureDirectoryExists($folder);

        $nama = 'uji-jpg-lama.jpg';
        $gambar = imagecreatetruecolor(40, 40);
        ob_start();
        imagejpeg($gambar);
        $isi = (string) ob_get_clean();
        file_put_contents($folder . '/' . $nama, $isi);

        try {
            $this->actingAs($user)
                ->get(route('branding.edit'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where(
                        'library.logo',
                        fn ($items) => collect($items)->contains(fn ($item) => $item['path'] === 'branding/' . $nama),
                    ),
                );
        } finally {
            File::delete($folder . '/' . $nama);
        }
    }
}
