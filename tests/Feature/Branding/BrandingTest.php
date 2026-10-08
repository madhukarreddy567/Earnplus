<?php

namespace Tests\Feature\Branding;

use App\Models\Setting;
use App\Services\BrandingService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\BrandingSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 8: branding media uploads, processing, cleanup, design settings
 * and layout consumption. All file work runs on fake storage.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(SettingSeeder::class);
        $this->seed(BrandingSeeder::class);
    }

    protected function loginAsAdmin(): void
    {
        $this->seed(AdminSeeder::class);

        $this->post('/admin/login', [
            'email' => 'admin@earnplus.local',
            'password' => 'ChangeMe123!',
        ]);

        $this->assertAuthenticatedAs(
            \App\Models\Admin::where('email', 'admin@earnplus.local')->first(),
            'admin'
        );
    }

    /**
     * Build a real image file on disk (GD) wrapped as an upload.
     */
    protected function makeImage(int $width, int $height, string $format = 'png'): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 14, 159, 110));
        $path = tempnam(sys_get_temp_dir(), 'ep-brand') . '.' . $format;

        match ($format) {
            'png' => imagepng($img, $path),
            'jpg', 'jpeg' => imagejpeg($img, $path, 85),
            'webp' => imagewebp($img, $path, 85),
            default => imagepng($img, $path),
        };

        imagedestroy($img);

        return new UploadedFile($path, "test.{$format}", mime_content_type($path), null, true);
    }

    // ------------------------------------------------------------------
    // Access gates
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.branding.index'))->assertRedirect(route('admin.login'));
    }

    public function test_user_guard_cannot_open_branding(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $this->get(route('admin.branding.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_branding_page_with_tabs_and_preview(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.branding.index'));

        $response->assertOk();
        $response->assertSee('Branding &amp; design', false);
        $response->assertSee('Live preview', false);
        $response->assertSee('?tab=design', false);
    }

    // ------------------------------------------------------------------
    // Upload validation
    // ------------------------------------------------------------------

    public function test_non_image_upload_is_rejected(): void
    {
        $this->loginAsAdmin();

        $response = $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame('', (string) setting('branding_logo', ''));
    }

    public function test_oversize_upload_is_rejected(): void
    {
        $this->loginAsAdmin();

        $response = $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('huge.png')->size(6000),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame('', (string) setting('branding_logo', ''));
    }

    public function test_unknown_asset_type_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'watermark',
            'file' => $this->makeImage(100, 100),
        ])->assertSessionHasErrors('type');
    }

    // ------------------------------------------------------------------
    // Processing profiles
    // ------------------------------------------------------------------

    public function test_logo_upload_is_resized_to_max_512_wide(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => $this->makeImage(1200, 800, 'png'),
        ])->assertSessionHasNoErrors();

        $path = setting('branding_logo');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        $size = getimagesize(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(512, $size[0]);
    }

    public function test_favicon_generates_32_and_180_variants(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'favicon',
            'file' => $this->makeImage(400, 400, 'png'),
        ])->assertSessionHasNoErrors();

        foreach (['branding_favicon_32' => 32, 'branding_favicon_180' => 180] as $key => $expected) {
            $path = setting($key);
            $this->assertNotEmpty($path);
            Storage::disk('public')->assertExists($path);
            $size = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame($expected, $size[0]);
            $this->assertSame($expected, $size[1]);
        }
    }

    public function test_banner_generates_responsive_variants(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'banner',
            'file' => $this->makeImage(2000, 1000, 'jpg'),
        ])->assertSessionHasNoErrors();

        $full = setting('branding_banner');
        $mid = setting('branding_banner_1200');
        $small = setting('branding_banner_768');

        $this->assertNotEmpty($full);
        $this->assertNotEmpty($mid);
        $this->assertNotEmpty($small);

        $this->assertLessThanOrEqual(1200, getimagesize(Storage::disk('public')->path($mid))[0]);
        $this->assertLessThanOrEqual(768, getimagesize(Storage::disk('public')->path($small))[0]);
    }

    public function test_avatar_is_cropped_to_256_square(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'avatar',
            'file' => $this->makeImage(900, 400, 'png'),
        ])->assertSessionHasNoErrors();

        $path = setting('branding_avatar');
        Storage::disk('public')->assertExists($path);
        $size = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame([256, 256], [$size[0], $size[1]]);
    }

    public function test_email_header_is_capped_at_600_wide(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'email_header',
            'file' => $this->makeImage(1400, 300, 'png'),
        ])->assertSessionHasNoErrors();

        $path = setting('branding_email_header');
        Storage::disk('public')->assertExists($path);
        $this->assertLessThanOrEqual(600, getimagesize(Storage::disk('public')->path($path))[0]);
    }

    // ------------------------------------------------------------------
    // Replace + remove cleanup
    // ------------------------------------------------------------------

    public function test_replacing_an_asset_deletes_the_old_files(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => $this->makeImage(800, 600, 'png'),
        ]);

        $first = setting('branding_logo');
        Storage::disk('public')->assertExists($first);

        $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => $this->makeImage(800, 600, 'jpg'),
        ]);

        $second = setting('branding_logo');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_removing_an_asset_clears_settings_and_files(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'favicon',
            'file' => $this->makeImage(300, 300, 'png'),
        ]);

        $paths = [setting('branding_favicon_32'), setting('branding_favicon_180')];
        $this->assertNotEmpty($paths[0]);

        $this->delete(route('admin.branding.media.destroy', 'favicon'))
            ->assertRedirect(route('admin.branding.index', ['tab' => 'media']));

        $this->assertSame('', (string) setting('branding_favicon_32', ''));
        $this->assertSame('', (string) setting('branding_favicon_180', ''));
        Storage::disk('public')->assertMissing($paths[0]);
        Storage::disk('public')->assertMissing($paths[1]);
    }

    public function test_removing_unknown_asset_type_404s(): void
    {
        $this->loginAsAdmin();

        $this->delete(route('admin.branding.media.destroy', 'watermark'))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Layout consumption
    // ------------------------------------------------------------------

    public function test_layout_uses_db_logo_and_favicon(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'logo',
            'file' => $this->makeImage(800, 600, 'png'),
        ]);
        $this->post(route('admin.branding.media.store'), [
            'type' => 'favicon',
            'file' => $this->makeImage(300, 300, 'png'),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        // Phase 10: stored filenames are randomized (logo-<token>.png).
        $this->assertMatchesRegularExpression(
            '#branding/logo-[0-9a-f]{16}\\.png#',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '#branding/favicon-32-[0-9a-f]{16}\\.png#',
            $response->getContent()
        );
        $response->assertSee('apple-touch-icon', false);
    }

    public function test_layout_falls_back_to_defaults_without_uploads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('branding/logo', false);
        $response->assertSee('data:image/svg+xml', false); // default emoji favicon
        $response->assertSee('EarnPlus', false);
    }

    public function test_dashboard_uses_db_banner_image_when_uploaded(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'banner',
            'file' => $this->makeImage(1600, 700, 'jpg'),
        ]);

        $user = \App\Models\User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Phase 10: stored filenames are randomized (banner-1200-<token>.jpg).
        $this->assertMatchesRegularExpression(
            '#branding/banner-1200-[0-9a-f]{16}\\.jpg#',
            $response->getContent()
        );
    }

    // ------------------------------------------------------------------
    // Design settings
    // ------------------------------------------------------------------

    public function test_design_settings_update_and_apply_instantly(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.design.store'), [
            'design_primary' => '#ff5733',
            'design_accent' => '#3355ff',
            'design_hero_from' => '#111111',
            'design_hero_to' => '#222222',
            'font_scale' => '110',
            'button_shape' => 'square',
            'theme' => 'dark',
            'background_style' => 'blur',
            'animations_enabled' => '0',
        ])->assertRedirect(route('admin.branding.index', ['tab' => 'design']));

        $this->assertSame('#ff5733', setting('design_primary'));
        $this->assertSame('square', setting('button_shape'));
        $this->assertSame('dark', setting('theme'));

        $response = $this->get('/');

        $response->assertSee('--ep-primary: #ff5733;', false);
        $response->assertSee('data-theme="dark"', false);
        $response->assertSee('data-btn-shape="square"', false);
        $response->assertSee('--ep-font-scale: 1.1;', false);
        $response->assertSee('class="no-anime"', false);
    }

    public function test_invalid_design_color_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.design.store'), [
            'design_primary' => 'not-a-color',
            'font_scale' => '100',
            'button_shape' => 'pill',
            'theme' => 'light',
            'background_style' => 'cover',
        ])->assertSessionHasErrors('design_primary');

        $this->assertSame('#0e9f6e', setting('design_primary'));
    }

    public function test_malformed_stored_color_falls_back_to_default(): void
    {
        Setting::set('design_primary', 'garbage', 'design');

        $this->assertSame('#0e9f6e', design_color('design_primary', '#0e9f6e'));

        $response = $this->get('/');
        $response->assertSee('--ep-primary: #0e9f6e;', false);
    }

    public function test_background_blur_and_cover_classes(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.branding.media.store'), [
            'type' => 'background',
            'file' => $this->makeImage(1600, 900, 'jpg'),
        ]);

        Setting::set('background_style', 'blur', 'design');
        $this->get('/')->assertSee('ep-bg-blur', false);

        Setting::set('background_style', 'cover', 'design');
        $this->get('/')->assertSee('ep-bg-cover', false);
    }

    public function test_shade_color_math(): void
    {
        $this->assertSame('#000000', shade_color('#ffffff', -100));
        $this->assertSame('#ffffff', shade_color('#000000', 100));
        $this->assertSame('#0e9f6e', shade_color('#0e9f6e', 0));
        // Darkening must actually darken each channel.
        $darker = shade_color('#0e9f6e', -14);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $darker);
        $this->assertLessThan(hexdec('0e'), hexdec(substr($darker, 1, 2)));
    }

    public function test_branding_seeder_never_overwrites_admin_choices(): void
    {
        Setting::set('design_primary', '#123456', 'design');

        $this->seed(BrandingSeeder::class);

        $this->assertSame('#123456', setting('design_primary'));
        // ...but missing keys still get their defaults.
        $this->assertSame('pill', setting('button_shape'));
    }
}
