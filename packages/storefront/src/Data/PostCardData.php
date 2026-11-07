<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkGenerator;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class PostCardData implements Arrayable
{
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public ?string $image,
        public ?string $excerpt,
        public ?CarbonInterface $publishedAt,
    ) {}

    /**
     * Pass one generator when building many cards: each new instance reads the permalink settings again.
     */
    public static function fromModel(Post $post, ?PermalinkGenerator $permalinks = null): self
    {
        $excerpt = filled($post->excerpt) ? $post->excerpt : strip_tags((string) $post->content);

        return new self(
            id: $post->id,
            title: $post->title,
            url: ($permalinks ?? resolve(PermalinkGenerator::class))->url($post),
            image: $post->featuredImage?->isImage() ? $post->featuredImage->url : null,
            excerpt: Str::limit(mb_trim(html_entity_decode($excerpt)), 160) ?: null,
            publishedAt: $post->published_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'image' => $this->image,
            'excerpt' => $this->excerpt,
            'published_at' => $this->publishedAt?->toIso8601String(),
        ];
    }
}
