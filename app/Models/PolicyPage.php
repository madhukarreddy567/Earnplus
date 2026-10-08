<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rich-text policy page (terms, privacy, refund, about).
 * Only the latest *published* version is shown publicly; every
 * save keeps a revision row so the owner can roll back.
 */
class PolicyPage extends Model
{
    public const SLUG_TERMS = 'terms';
    public const SLUG_PRIVACY = 'privacy';
    public const SLUG_REFUND = 'refund';
    public const SLUG_ABOUT = 'about';

    /** @var list<string> */
    public const SLUGS = [
        self::SLUG_TERMS,
        self::SLUG_PRIVACY,
        self::SLUG_REFUND,
        self::SLUG_ABOUT,
    ];

    /** @var array<string, string> */
    public const TITLES = [
        self::SLUG_TERMS => 'Terms of Service',
        self::SLUG_PRIVACY => 'Privacy Policy',
        self::SLUG_REFUND => 'Refund Policy',
        self::SLUG_ABOUT => 'About Us',
    ];

    protected $fillable = [
        'slug',
        'title',
        'body_html',
        'version',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PolicyPageRevision::class)->latest('version');
    }

    /**
     * The version the public sees — latest published row.
     */
    public static function published(string $slug): ?static
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    /**
     * Save new content: sanitizes the HTML, bumps the version,
     * and archives the previous version as a revision.
     */
    public function saveNewVersion(string $bodyHtml, bool $publish): static
    {
        $clean = app(\App\Services\HtmlSanitizer::class)->sanitize($bodyHtml);

        if ($this->exists && $this->body_html !== null) {
            PolicyPageRevision::create([
                'policy_page_id' => $this->id,
                'version' => $this->version,
                'body_html' => $this->body_html,
                'was_published' => $this->is_published,
            ]);
        }

        $this->version = ($this->version ?? 0) + 1;
        $this->body_html = $clean;
        $this->is_published = $publish;

        if ($publish) {
            $this->published_at = now();
        }

        $this->save();

        return $this;
    }
}
