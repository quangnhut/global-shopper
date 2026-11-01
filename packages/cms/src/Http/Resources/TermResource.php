<?php

declare(strict_types=1);

namespace Shopper\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkGenerator;

/**
 * @mixin PostCategory|PostTag
 */
final class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generator = resolve(PermalinkGenerator::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->resource instanceof PostCategory ? $this->resource->parent_id : null,
            'path' => $generator->path($this->resource),
            'url' => $generator->url($this->resource),
        ];
    }
}
