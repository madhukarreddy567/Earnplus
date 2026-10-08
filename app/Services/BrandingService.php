<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Branding media management for EarnPlus (Phase 8).
 *
 * Admins upload logo / favicon / banner / background / email header /
 * default avatar. Every asset is processed with intervention/image into
 * the right sizes, stored on the public disk under storage/app/public/
 * branding/, and the resulting paths are saved in DB settings
 * (branding_*). Replacing an asset deletes the old files first.
 *
 * Views consume the assets through the branding_url() helper, which
 * returns an absolute URL with a cache-busting mtime query string —
 * or null when nothing was uploaded (views then render defaults).
 */
class BrandingService
{
    public const DISK = 'public';

    public const DIR = 'branding';

    public const MAX_KB = 5120; // 5 MB

    /**
     * Asset type => processing profile.
     *
     * Each profile lists the DB setting keys it owns (so replace/delete
     * can clean up exactly those files).
     */
    public const TYPES = [
        'logo' => [
            'label' => 'Logo',
            'hint' => 'PNG or WebP with transparency. Auto-resized to max 512px wide.',
            'settings' => ['branding_logo'],
        ],
        'favicon' => [
            'label' => 'Favicon',
            'hint' => 'Generates a 32px favicon and a 180px Apple touch icon.',
            'settings' => ['branding_favicon_32', 'branding_favicon_180'],
        ],
        'banner' => [
            'label' => 'Banner',
            'hint' => 'Responsive: stores the original plus 1200px and 768px variants.',
            'settings' => ['branding_banner', 'branding_banner_1200', 'branding_banner_768'],
        ],
        'background' => [
            'label' => 'Background image',
            'hint' => 'Page background. Fit (cover) or blur is chosen in Design settings.',
            'settings' => ['branding_background'],
        ],
        'email_header' => [
            'label' => 'Email header',
            'hint' => 'Top image for verification / password e-mails. Max 600px wide.',
            'settings' => ['branding_email_header'],
        ],
        'avatar' => [
            'label' => 'Default avatar',
            'hint' => 'Square-cropped to 256px. Shown when a user has no profile picture.',
            'settings' => ['branding_avatar'],
        ],
    ];

    protected ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver());
    }

    /**
     * Process and store an uploaded asset. Returns [setting_key => path].
     *
     * @throws \InvalidArgumentException for unknown types
     */
    public function store(UploadedFile $file, string $type): array
    {
        if (! isset(static::TYPES[$type])) {
            throw new \InvalidArgumentException("Unknown branding asset type: {$type}");
        }

        // Phase 10 upload hardening:
        // 1. Real content check — getimagesize reads the actual image header.
        //    Anything that is not a genuine image (or is absurdly large)
        //    fails here, before the old asset is touched.
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ($info[2] !== IMAGETYPE_JPEG && $info[2] !== IMAGETYPE_PNG && $info[2] !== IMAGETYPE_WEBP)) {
            throw new \InvalidArgumentException('The uploaded file is not a valid JPG, PNG or WebP image.');
        }
        if ($info[0] > 6000 || $info[1] > 6000) {
            throw new \InvalidArgumentException('The image is too large (max 6000×6000 pixels).');
        }

        // 2. Extension comes from the verified content, never the client.
        $extension = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        };

        // Decode once up front: a corrupt file must fail BEFORE the old
        // asset is touched, so the site never ends up with nothing.
        // Re-encoding through intervention strips any embedded payloads.
        $this->images->decode($file->getRealPath());

        // Remove the previous files first so stale variants never linger.
        $this->deleteFiles($type);

        // 3. Randomized filenames: stored names are unpredictable, so an
        //    attacker can never guess or overwrite another asset's path.
        $token = bin2hex(random_bytes(8));

        $paths = match ($type) {
            'logo' => $this->storeLogo($file, $extension, $token),
            'favicon' => $this->storeFavicon($file, $token),
            'banner' => $this->storeBanner($file, $extension, $token),
            'background' => $this->storeBackground($file, $extension, $token),
            'email_header' => $this->storeEmailHeader($file, $extension, $token),
            'avatar' => $this->storeAvatar($file, $extension, $token),
        };

        foreach ($paths as $settingKey => $path) {
            Setting::set($settingKey, $path, 'branding');
        }

        return $paths;
    }

    /**
     * Delete an asset's files and clear its DB settings.
     */
    public function delete(string $type): void
    {
        if (! isset(static::TYPES[$type])) {
            throw new \InvalidArgumentException("Unknown branding asset type: {$type}");
        }

        $this->deleteFiles($type);
    }

    /**
     * Absolute public URL for a branding setting key, with a cache-busting
     * mtime query string. Null when nothing is uploaded (or the file is
     * gone) so views can render their default.
     */
    public function url(string $settingKey): ?string
    {
        $path = setting($settingKey);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk(static::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $url = url($disk->url($path));

        $mtime = $disk->lastModified($path);

        return $mtime ? "{$url}?v={$mtime}" : $url;
    }

    /**
     * Setting keys owned by one asset type (for forms and cleanup).
     *
     * @return string[]
     */
    public static function settingsFor(string $type): array
    {
        return static::TYPES[$type]['settings'] ?? [];
    }

    /**
     * Design defaults seeded by BrandingSeeder (firstOrCreate — never
     * overwrites values the admin already customized).
     *
     * @return array<int, array{key: string, value: string}>
     */
    public static function designDefaults(): array
    {
        return [
            ['key' => 'design_primary', 'value' => '#0e9f6e'],
            ['key' => 'design_accent', 'value' => '#0ea5e9'],
            ['key' => 'design_hero_from', 'value' => '#0e9f6e'],
            ['key' => 'design_hero_to', 'value' => '#075e43'],
            ['key' => 'font_scale', 'value' => '100'],
            ['key' => 'button_shape', 'value' => 'pill'],
            ['key' => 'theme', 'value' => 'light'],
            ['key' => 'background_style', 'value' => 'cover'],
            ['key' => 'animations_enabled', 'value' => '1'],
        ];
    }

    // ------------------------------------------------------------------
    // Processing profiles
    // ------------------------------------------------------------------

    protected function storeLogo(UploadedFile $file, string $extension, string $token): array
    {
        $path = static::DIR . "/logo-{$token}.{$extension}";

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 512)
            ->save($this->absolute($path));

        return ['branding_logo' => $path];
    }

    protected function storeFavicon(UploadedFile $file, string $token): array
    {
        $paths = [
            'branding_favicon_32' => static::DIR . "/favicon-32-{$token}.png",
            'branding_favicon_180' => static::DIR . "/favicon-180-{$token}.png",
        ];

        $this->images->decode($file->getRealPath())
            ->cover(32, 32)
            ->save($this->absolute($paths['branding_favicon_32']));

        $this->images->decode($file->getRealPath())
            ->cover(180, 180)
            ->save($this->absolute($paths['branding_favicon_180']));

        return $paths;
    }

    protected function storeBanner(UploadedFile $file, string $extension, string $token): array
    {
        $paths = [
            'branding_banner' => static::DIR . "/banner-{$token}.{$extension}",
            'branding_banner_1200' => static::DIR . "/banner-1200-{$token}.{$extension}",
            'branding_banner_768' => static::DIR . "/banner-768-{$token}.{$extension}",
        ];

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 2400)
            ->save($this->absolute($paths['branding_banner']));

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 1200)
            ->save($this->absolute($paths['branding_banner_1200']));

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 768)
            ->save($this->absolute($paths['branding_banner_768']));

        return $paths;
    }

    protected function storeBackground(UploadedFile $file, string $extension, string $token): array
    {
        $path = static::DIR . "/background-{$token}.{$extension}";

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 1920)
            ->save($this->absolute($path));

        return ['branding_background' => $path];
    }

    protected function storeEmailHeader(UploadedFile $file, string $extension, string $token): array
    {
        $path = static::DIR . "/email-header-{$token}.{$extension}";

        $this->images->decode($file->getRealPath())
            ->scaleDown(width: 600)
            ->save($this->absolute($path));

        return ['branding_email_header' => $path];
    }

    protected function storeAvatar(UploadedFile $file, string $extension, string $token): array
    {
        $path = static::DIR . "/avatar-{$token}.{$extension}";

        $this->images->decode($file->getRealPath())
            ->cover(256, 256)
            ->save($this->absolute($path));

        return ['branding_avatar' => $path];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function deleteFiles(string $type): void
    {
        $disk = Storage::disk(static::DISK);

        foreach (static::settingsFor($type) as $key) {
            $path = setting($key);

            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }

            Setting::set($key, '', 'branding');
        }
    }

    protected function absolute(string $path): string
    {
        $disk = Storage::disk(static::DISK);
        $disk->makeDirectory(dirname($path));

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        return $disk->path($path);
    }
}
