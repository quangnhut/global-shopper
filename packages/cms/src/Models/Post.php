<?php

declare(strict_types=1);

namespace Shopper\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shopper\Cms\Database\Factories\PostFactory;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Enums\RevisionKind;
use Shopper\Cms\Models\Contracts\Post as PostContract;
use Shopper\Cms\PostTypes\PostType;
use Shopper\Cms\PostTypes\PostTypeRegistry;
use Shopper\Core\Models\Upload;
use Shopper\Core\Traits\HasModelContract;

/**
 * @property-read int $id
 * @property-read string $type
 * @property-read string $title
 * @property-read string $slug
 * @property-read ?string $excerpt
 * @property-read ?string $content
 * @property-read PostStatus $status
 * @property-read ?string $password
 * @property-read int $menu_order
 * @property-read ?string $template
 * @property-read ?int $author_id
 * @property-read ?int $parent_id
 * @property-read ?int $featured_upload_id
 * @property-read ?int $primary_category_id
 * @property-read array<string, mixed>|null $metadata
 * @property-read ?CarbonInterface $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read ?CarbonInterface $deleted_at
 * @property-read ?Post $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Post> $children
 * @property-read ?Upload $featuredImage
 * @property-read ?PostCategory $primaryCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostCategory> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostTag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostRevision> $revisions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PostRevision> $autosaves
 */
class Post extends Model implements PostContract
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasModelContract;
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    public static function configuredClass(): string
    {
        return config('shopper.cms.models.post', static::class);
    }

    public function getTable(): string
    {
        return shopper_table('cms_posts');
    }

    public function postType(): PostType
    {
        return resolve(PostTypeRegistry::class)->get($this->type);
    }

    public function visibility(): PostVisibility
    {
        return match (true) {
            $this->status === PostStatus::Private => PostVisibility::Private,
            $this->isPasswordProtected() => PostVisibility::Password,
            default => PostVisibility::Public,
        };
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Scheduled;
    }

    public function isPasswordProtected(): bool
    {
        return filled($this->password);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'author_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::configuredClass(), 'parent_id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::configuredClass(), 'parent_id')->orderBy('menu_order');
    }

    /**
     * @return BelongsTo<Upload, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'featured_upload_id');
    }

    /**
     * @return BelongsToMany<PostCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, shopper_table('cms_category_post'), 'post_id', 'category_id');
    }

    /**
     * @return BelongsToMany<PostTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PostTag::class, shopper_table('cms_post_tag'), 'post_id', 'tag_id');
    }

    /**
     * The category used for `%category%` permalinks and breadcrumbs (Yoast's "primary category").
     *
     * @return BelongsTo<PostCategory, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'primary_category_id');
    }

    /**
     * Saved versions, newest first.
     *
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'post_id')
            ->where('kind', RevisionKind::Revision)
            ->latest('id');
    }

    /**
     * One per user at most (see `AutosavePostAction`).
     *
     * @return HasMany<PostRevision, $this>
     */
    public function autosaves(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'post_id')->where('kind', RevisionKind::Autosave);
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Live content anyone may see: published, with a publish date that has passed.
     * Password-protected posts are included (WordPress lists them, the content stays locked).
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeWithStatus(Builder $query, PostStatus $status): void
    {
        $query->where('status', $status);
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'menu_order' => 'integer',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
