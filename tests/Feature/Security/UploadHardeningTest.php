<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Services\BrandingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Storage::fake('public');
    }

    protected function branding(): BrandingService
    {
        return app(BrandingService::class);
    }

    public function test_fake_image_with_real_extension_is_rejected(): void
    {
        // Text content wearing a .png name: getimagesize fails.
        $file = UploadedFile::fake()->create('evil.png', 100, 'image/png');

        $this->expectException(\InvalidArgumentException::class);
        $this->branding()->store($file, 'logo');
    }

    public function test_oversized_dimensions_are_rejected_before_processing(): void
    {
        // Header-only PNG claiming 7000×7000 (no pixel data needed —
        // getimagesize reads the IHDR chunk).
        $path = tempnam(sys_get_temp_dir(), 'huge') . '.png';
        file_put_contents($path, $this->pngWithDimensions(7000, 7000));
        $file = new UploadedFile($path, 'huge.png', 'image/png', null, true);

        try {
            $this->branding()->store($file, 'logo');
            $this->fail('Oversized image should have been rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('too large', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function test_extension_comes_from_content_not_client(): void
    {
        // A real PNG presented with a .jpg client name must be stored as .png.
        $real = UploadedFile::fake()->image('avatar.png', 200, 200);
        $file = new UploadedFile($real->getRealPath(), 'avatar.jpg', 'image/jpeg', null, true);

        $paths = $this->branding()->store($file, 'avatar');

        $stored = (string) array_values($paths)[0];
        $this->assertStringEndsWith('.png', $stored);
    }

    public function test_stored_filenames_are_randomized(): void
    {
        $first = $this->branding()->store(UploadedFile::fake()->image('a.png', 200, 200), 'avatar');
        $second = $this->branding()->store(UploadedFile::fake()->image('b.png', 200, 200), 'avatar');

        $this->assertNotSame(
            (string) array_values($first)[0],
            (string) array_values($second)[0]
        );
    }

    public function test_valid_upload_still_works_end_to_end(): void
    {
        $paths = $this->branding()->store(UploadedFile::fake()->image('logo.png', 400, 200), 'logo');

        $stored = (string) array_values($paths)[0];
        $this->assertTrue(Storage::disk('public')->exists($stored));
        $this->assertSame($stored, Setting::get('branding_logo'));
    }

    /**
     * Minimal PNG with the given IHDR dimensions (no IDAT needed —
     * getimagesize only parses the header).
     */
    protected function pngWithDimensions(int $w, int $h): string
    {
        $ihdr = pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
        $chunk = function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', $ihdr)
            . $chunk('IEND', '');
    }
}
