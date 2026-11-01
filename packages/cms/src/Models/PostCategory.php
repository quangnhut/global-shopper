<?php

declare(strict_types=1);

namespace Shopper\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Shopper\Cms\Database\Factories\PostCategoryFactory;
use Shopper\Core\Models\Traits\HasSlug;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read ?string $description
 * @property-read int $position
 * @property-read ?int $parent_id
 * @property-read array<string, mixed>|null $metadata
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read ?PostCategory $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostCategory> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $posts
 */
class PostCategory extends Model
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    use HasRecursiveRelationships;
    use HasSlug;

    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('cms_post_categories');
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::configuredClass(), shopper_table('cms_category_post'), 'category_id', 'post_id');
    }

    protected static function booted(): void
    {
        static::saving(function (PostCategory $category): void {
            if (blank($category->getAttribute('slug'))) {
                $category->setAttribute('slug', $category->name);
            }
        });
    }

    protected static function newFactory(): PostCategoryFactory
    {
        return PostCategoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'metadata' => 'array',
        ];
    }
}
