<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;

/**
 * Builds the public path / URL of posts, pages, categories and tags (WordPress's get_permalink()).
 */
final class PermalinkGenerator
{
    public function __construct(private readonly PermalinkSettings $settings) {}

    public function url(Model $model): string
    {
        $base = mb_rtrim((string) (config('shopper.cms.frontend_url') ?: config('app.url')), '/');

        return $base.$this->path($model);
    }

    public function path(Model $model): string
    {
        return match (true) {
            $model instanceof Post => $this->postPath($model),
            $model instanceof PostCategory => $this->slashed('/'.$this->settings->categoryBase().'/'.$this->categorySlugs($model)),
            $model instanceof PostTag => $this->slashed('/'.$this->settings->tagBase().'/'.$model->slug),
            default => throw new InvalidArgumentException('No permalink for ['.$model::class.'].'),
        };
    }

    /**
     * Path of a post for a given structure (used for redirects from old structures).
     */
    public function postPath(Post $post, ?PermalinkStructure $structure = null): string
    {
        if ($post->id === $this->settings->frontPageId()) {
            return '/';
        }

        $type = $post->postType();

        if ($type->hierarchical) {
            return $this->slashed('/'.implode('/', [...$this->ancestorSlugs($post), $post->slug]));
        }

        if ($type->key !== 'post') {
            return $this->slashed("/{$type->key}/{$post->slug}");
        }

        $structure ??= $this->settings->structure();

        if ($structure->isPlain()) {
            return '/?p='.$post->id;
        }

        $date = $post->published_at ?? $post->created_at ?? now();

        return $structure->build([
            'year' => $date->format('Y'),
            'monthnum' => $date->format('m'),
            'day' => $date->format('d'),
            'hour' => $date->format('H'),
            'minute' => $date->format('i'),
            'second' => $date->format('s'),
            'post_id' => $post->id,
            'postname' => $post->slug,
            'category' => in_array('%category%', $structure->tags(), true) ? $this->postCategoryPath($post) : '',
            'author' => in_array('%author%', $structure->tags(), true) ? $this->authorSlug($post) : '',
        ]);
    }

    public function authorSlug(Post $post): string
    {
        $author = $post->author;

        return $author !== null
            ? (Str::slug((string) $author->getAttribute('full_name')) ?: 'author-'.$author->getKey())
            : 'author';
    }

    /**
     * Nested category path for `%category%`: the primary category, else the default one.
     */
    public function postCategoryPath(Post $post): string
    {
        $category = $post->primaryCategory
            ?? $post->categories()->orderBy('id')->first()
            ?? resolve(ResolveDefaultCategoryAction::class)->execute();

        return $this->categorySlugs($category);
    }

    public function categorySlugs(PostCategory $category): string
    {
        $slugs = [$category->slug];
        $seen = [$category->id => true];

        while ($category->parent_id !== null && ! isset($seen[$category->parent_id]) && ($category = $category->parent) !== null) {
            $seen[$category->id] = true;
            array_unshift($slugs, $category->slug);
        }

        return implode('/', $slugs);
    }

    /**
     * @return list<string>
     */
    private function ancestorSlugs(Post $post): array
    {
        $slugs = [];
        $seen = [$post->id => true];

        while ($post->parent_id !== null && ! isset($seen[$post->parent_id]) && ($post = $post->parent) !== null) {
            $seen[$post->id] = true;
            array_unshift($slugs, $post->slug);
        }

        return $slugs;
    }

    private function slashed(string $path): string
    {
        $path = mb_rtrim($path, '/');

        return $this->settings->trailingSlash() ? $path.'/' : $path;
    }
}
