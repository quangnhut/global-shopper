<?php

declare(strict_types=1);

namespace Shopper\Seo\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-entry SEO overrides. Empty fields fall back to the type's templates and defaults.
 *
 * @property-read int $id
 * @property-read string $seoable_type
 * @property-read int $seoable_id
 * @property-read ?string $title
 * @property-read ?string $description
 * @property-read ?string $focus_keyword
 * @property-read ?string $canonical_url
 * @property-read ?bool $robots_index
 * @property-read ?bool $robots_follow
 * @property-read array<string, mixed>|null $robots_advanced
 * @property-read bool $exclude_from_sitemap
 * @property-read ?string $breadcrumb_title
 * @property-read ?string $og_title
 * @property-read ?string $og_description
 * @property-read ?int $og_image_upload_id
 * @property-read ?string $twitter_title
 * @property-read ?string $twitter_description
 * @property-read ?int $twitter_image_upload_id
 * @property-read ?string $schema_type
 * @property-read array<array-key, mixed>|null $schema_custom
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
class SeoMeta extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('seo_meta');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'robots_advanced' => 'array',
            'exclude_from_sitemap' => 'boolean',
            'schema_custom' => 'array',
        ];
    }
}
