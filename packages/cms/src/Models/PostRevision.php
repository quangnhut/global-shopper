<?php

declare(strict_types=1);

namespace Shopper\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shopper\Cms\Enums\RevisionKind;

/**
 * A saved copy of a post's title, content and excerpt (WordPress's revisions and autosaves).
 *
 * @property-read int $id
 * @property-read int $post_id
 * @property-read ?int $user_id
 * @property-read RevisionKind $kind
 * @property-read string $title
 * @property-read ?string $content
 * @property-read ?string $excerpt
 * @property-read array<string, mixed>|null $meta
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Post $post
 * @property-read ?Model $user
 */
class PostRevision extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('cms_post_revisions');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::configuredClass(), 'post_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => RevisionKind::class,
            'meta' => 'array',
        ];
    }
}
