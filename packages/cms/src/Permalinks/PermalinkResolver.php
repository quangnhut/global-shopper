<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Models\Redirect;
use Shopper\Cms\PostTypes\PostTypeRegistry;

/**
 * Turns a public path into the content it points to (WordPress's rewrite rules + redirect_canonical).
 * The resolver does not check whether the content may be shown: callers decide (published, private…).
 */
final class PermalinkResolver
{
    public function __construct(
        private readonly PermalinkSettings $settings,
        private readonly PermalinkGenerator $generator,
        private readonly PostTypeRegistry $postTypes,
    ) {}

    public function resolve(string $url): ?ResolvedPermalink
    {
        $path = mb_trim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if ($path === '') {
            return $this->home($query);
        }

        $segments = explode('/', $path);

        if (ReservedPaths::contains($segments[0])) {
            return null;
        }

        return $this->taxonomy($segments, $path)
            ?? $this->page($segments, $path)
            ?? $this->customType($segments, $path)
            ?? $this->post($path)
            ?? $this->oldStructure($path)
            ?? $this->redirect($path);
    }

    /**
     * Stored redirects (automatic after URL changes, or manual). Each hit is counted.
     */
    private function redirect(string $path): ?ResolvedPermalink
    {
        $redirect = Redirect::query()->where('from_path', Redirect::normalize($path))->first();

        if ($redirect === null) {
            return null;
        }

        $redirect->forceFill(['hits' => $redirect->hits + 1, 'last_hit_at' => now()])->saveQuietly();

        return $redirect->status_code === 410 || $redirect->to_path === null
            ? new ResolvedPermalink('gone', status: 410)
            : ResolvedPermalink::redirect($redirect->to_path, $redirect->status_code);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function home(array $query): ?ResolvedPermalink
    {
        if (filled($query['p'] ?? null) && ($post = $this->postQuery('post')->find((int) $query['p'])) !== null) {
            return $this->canonical($post, '/?p='.$post->id);
        }

        if (($id = $this->settings->frontPageId()) !== null && ($page = $this->postQuery('page')->find($id)) !== null) {
            return new ResolvedPermalink('page', $page);
        }

        return null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function taxonomy(array $segments, string $path): ?ResolvedPermalink
    {
        if (count($segments) < 2) {
            return null;
        }

        if ($segments[0] === $this->settings->categoryBase()) {
            $category = PostCategory::query()->where('slug', end($segments))->first();

            return $category === null ? null : $this->canonical($category, $path, 'category');
        }

        if ($segments[0] === $this->settings->tagBase() && count($segments) === 2) {
            $tag = PostTag::query()->where('slug', $segments[1])->first();

            return $tag === null ? null : $this->canonical($tag, $path, 'tag');
        }

        return null;
    }

    /**
     * @param  list<string>  $segments
     */
    private function page(array $segments, string $path): ?ResolvedPermalink
    {
        $candidates = $this->postQuery('page')->where('slug', end($segments))->get();

        foreach ($candidates as $page) {
            if (mb_trim($this->generator->path($page), '/') === $path) {
                return new ResolvedPermalink('page', $page);
            }
        }

        return null;
    }

    /**
     * Other registered, non-hierarchical types live under `/{type}/{slug}`.
     *
     * @param  list<string>  $segments
     */
    private function customType(array $segments, string $path): ?ResolvedPermalink
    {
        if (count($segments) !== 2 || in_array($segments[0], ['post', 'page'], true) || ! $this->postTypes->has($segments[0])) {
            return null;
        }

        $post = $this->postQuery($segments[0])->where('slug', $segments[1])->first();

        return $post === null ? null : $this->canonical($post, $path, $segments[0]);
    }

    private function post(string $path): ?ResolvedPermalink
    {
        $post = $this->matchStructure($this->settings->structure(), $path);

        return $post === null ? null : $this->canonical($post, $path);
    }

    /**
     * Links built with a previous structure redirect to the current one.
     */
    private function oldStructure(string $path): ?ResolvedPermalink
    {
        foreach ($this->settings->history() as $structure) {
            if (($post = $this->matchStructure($structure, $path)) !== null) {
                return ResolvedPermalink::redirect($this->generator->path($post));
            }
        }

        return null;
    }

    private function matchStructure(PermalinkStructure $structure, string $path): ?Post
    {
        if ($structure->isPlain() || ! preg_match($structure->regex(), $path, $matches)) {
            return null;
        }

        $query = $this->postQuery('post');

        return match (true) {
            filled($matches['post_id'] ?? null) => $query->find((int) $matches['post_id']),
            filled($matches['postname'] ?? null) => $query->where('slug', $matches['postname'])->first(),
            default => null,
        };
    }

    /**
     * Found content whose canonical path differs (wrong date, old category…) redirects to it.
     */
    private function canonical(Post|PostCategory|PostTag $model, string $path, ?string $type = null): ResolvedPermalink
    {
        $canonical = $this->generator->path($model);

        if (mb_trim($canonical, '/') !== mb_trim($path, '/') && $canonical !== $path) {
            return ResolvedPermalink::redirect($canonical);
        }

        return new ResolvedPermalink($type ?? ($model instanceof Post ? $model->type : 'post'), $model);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Post>
     */
    private function postQuery(string $type)
    {
        return Post::configuredClass()::query()->ofType($type);
    }
}
