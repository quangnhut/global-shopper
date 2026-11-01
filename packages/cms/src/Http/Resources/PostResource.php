<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Cms\Support\ContentVisibility;

/**
 * Public representation of a post or page. Password-protected content only includes `content` and
 * `excerpt` when the right password is sent in the `X-Post-Password` header.
 *
 * @mixin Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generator = resolve(PermalinkGenerator::class);
        $unlocked = ContentVisibility::unlocks($this->resource, $request->header('X-Post-Password'));

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $generator->path($this->resource),
            'url' => $generator->url($this->resource),
            'protected' => $this->isPasswordProtected(),
            'excerpt' => $unlocked ? $this->excerpt : null,
            'content' => $unlocked ? $this->content : null,
            'template' => $this->template,
            'menu_order' => $this->menu_order,
            'parent_id' => $this->parent_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'modified_at' => $this->updated_at->toIso8601String(),
            'author' => $this->author === null ? null : [
                'id' => $this->author->getKey(),
                'name' => $this->author->getAttribute('full_name'),
                'slug' => $generator->authorSlug($this->resource),
            ],
            'featured_image' => $this->featuredImage === null ? null : [
                'id' => $this->featuredImage->id,
                'url' => $this->featuredImage->url,
                'name' => $this->featuredImage->name,
            ],
            'categories' => TermResource::collection($this->whenLoaded('categories')),
            'primary_category_id' => $this->primary_category_id,
            'tags' => TermResource::collection($this->whenLoaded('tags')),
        ];
    }
}
