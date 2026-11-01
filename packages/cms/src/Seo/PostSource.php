<?php

declare(strict_types=1);

namespace Shopper\Cms\Seo;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Cms\Permalinks\PermalinkSettings;
use Shopper\Cms\Support\ContentVisibility;
use Shopper\Seo\Contracts\SeoSource;
use Shopper\Seo\Schema\GraphBuilder;
use Shopper\Seo\Schema\GraphContext;
use Shopper\Seo\Support\TemplateRenderer;

/**
 * Posts, pages and other CMS post types for the SEO output.
 */
final class PostSource implements SeoSource
{
    public function __construct(
        private readonly PermalinkGenerator $permalinks,
        private readonly PermalinkSettings $settings,
    ) {}

    public function url(Model $model): string
    {
        return $this->permalinks->url($model);
    }

    /**
     * @param  Post  $model
     */
    public function isPublic(Model $model): bool
    {
        return ContentVisibility::isPublic($model);
    }

    /**
     * Password-protected content never leaks its text into descriptions.
     *
     * @param  Post  $model
     */
    public function variables(Model $model): array
    {
        $excerpt = $model->isPasswordProtected() ? null : TemplateRenderer::excerpt($model->excerpt ?: $model->content);

        return [
            'title' => $model->title,
            'excerpt' => $excerpt,
            'description' => $excerpt,
            'category' => ($model->primaryCategory ?? $model->categories->first())?->name,
            'tag' => $model->tags->first()?->name,
            'author' => $model->author?->getAttribute('full_name'),
            'date' => $model->published_at?->translatedFormat('j F Y'),
            'modified' => $model->updated_at->translatedFormat('j F Y'),
        ];
    }

    /**
     * @param  Post  $model
     */
    public function image(Model $model): ?string
    {
        $image = $model->featuredImage;

        return $image !== null && $image->isImage() ? $image->url : null;
    }

    /**
     * @param  Post  $model
     */
    public function ogType(Model $model): string
    {
        return $model->postType()->hierarchical || $model->id === $this->settings->frontPageId() ? 'website' : 'article';
    }

    /**
     * @param  Post  $model
     */
    public function publishedAt(Model $model): ?CarbonInterface
    {
        return $model->published_at;
    }

    /**
     * @param  Post  $model
     */
    public function modifiedAt(Model $model): CarbonInterface
    {
        return $model->updated_at;
    }

    public function isEmpty(Model $model): bool
    {
        return false;
    }

    /**
     * Pages: their parents; posts: their primary category (with its parents), like Yoast.
     *
     * @param  Post  $model
     */
    public function breadcrumbs(Model $model): array
    {
        if ($model->id === $this->settings->frontPageId()) {
            return [];
        }

        $trail = [];

        if ($model->postType()->hierarchical) {
            $parent = $model->parent;
            $seen = [];

            while ($parent !== null && ! isset($seen[$parent->id])) {
                $seen[$parent->id] = true;
                array_unshift($trail, ['name' => $parent->title, 'url' => $this->url($parent)]);
                $parent = $parent->parent;
            }
        } elseif (($category = $model->primaryCategory ?? $model->categories->first()) !== null) {
            $trail = resolve(TermSource::class)->breadcrumbs($category);
        }

        return [...$trail, ['name' => $model->title, 'url' => $this->url($model)]];
    }

    /**
     * An Article (or BlogPosting / NewsArticle) for posts; pages are described by their WebPage node.
     *
     * @param  Post  $model
     */
    public function schema(Model $model, GraphContext $context): array
    {
        if (! in_array($context->schemaType, GraphBuilder::ARTICLE_TYPES, true)) {
            return [];
        }

        $author = $model->author;

        return [GraphBuilder::filter([
            '@type' => $context->schemaType,
            '@id' => $context->id('article'),
            'isPartOf' => ['@id' => $context->webpageId],
            'mainEntityOfPage' => ['@id' => $context->webpageId],
            'headline' => mb_substr($model->title, 0, 110),
            'description' => $context->description,
            'image' => $context->image,
            'datePublished' => $context->datePublished,
            'dateModified' => $context->dateModified,
            'author' => $author === null ? null : [
                '@type' => 'Person',
                'name' => (string) $author->getAttribute('full_name'),
            ],
            'publisher' => ['@id' => $context->publisherId],
            'articleSection' => $model->categories->pluck('name')->all(),
            'keywords' => $model->tags->pluck('name')->all(),
            'wordCount' => $model->isPasswordProtected() ? null : str_word_count(strip_tags((string) $model->content)),
            'inLanguage' => $context->language,
        ])];
    }

    /**
     * Published, public, not password-protected entries of one post type.
     */
    public function sitemapQuery(string $type): Builder
    {
        return Post::configuredClass()::query()
            ->with('featuredImage')
            ->where('type', $type)
            ->published()
            ->whereNull('password')
            ->orderBy('id');
    }

    /**
     * @param  Post  $model
     */
    public function sitemapImages(Model $model): array
    {
        return array_filter([$this->image($model)]);
    }
}
