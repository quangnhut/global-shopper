<?php

declare(strict_types=1);

namespace Shopper\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Shopper\Cms\Database\Factories\PostTagFactory;
use Shopper\Core\Models\Traits\HasSlug;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read ?string $description
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $posts
 */
class PostTag extends Model
{
    /** @use HasFactory<PostTagFactory> */
    use HasFactory;

    use HasSlug;

    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('cms_post_tags');
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::configuredClass(), shopper_table('cms_post_tag'), 'tag_id', 'post_id');
    }

    protected static function booted(): void
    {
        static::saving(function (PostTag $tag): void {
            if (blank($tag->getAttribute('slug'))) {
                $tag->setAttribute('slug', $tag->name);
            }
        });
    }

    protected static function newFactory(): PostTagFactory
    {
        return PostTagFactory::new();
    }
}
