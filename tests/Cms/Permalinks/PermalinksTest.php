<?php

declare(strict_types=1);

use Shopper\Cms\Actions\SavePostCategoryAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Cms\Permalinks\PermalinkGenerator;
use Shopper\Cms\Permalinks\PermalinkResolver;
use Shopper\Cms\Permalinks\PermalinkSettings;
use Shopper\Cms\Permalinks\PermalinkStructure;
use Shopper\Cms\Permalinks\ReservedPaths;

uses(Tests\Cms\TestCase::class);

function generator(): PermalinkGenerator
{
    return resolve(PermalinkGenerator::class);
}

function resolver(): PermalinkResolver
{
    return resolve(PermalinkResolver::class);
}

function useStructure(string $structure): void
{
    resolve(PermalinkSettings::class)->save(['structure' => $structure]);
}

beforeEach(function (): void {
    $this->post = Post::factory()->published()->create([
        'slug' => 'hello-world',
        'published_at' => '2026-10-05 09:30:00',
    ]);
});

describe(PermalinkStructure::class, function (): void {
    it('validates structures like WordPress', function (string $structure, array $errors): void {
        expect((new PermalinkStructure($structure))->errors(ReservedPaths::all()))->toBe($errors);
    })->with([
        'post name' => ['/%postname%/', []],
        'plain' => ['', []],
        'numeric' => ['/archives/%post_id%', []],
        'no identifier' => ['/%year%/%monthnum%/', ['shopper-cms::permalinks.errors.needs_identifier']],
        'unknown tag' => ['/%foo%/%postname%/', ['shopper-cms::permalinks.errors.unknown_tag']],
        'reserved prefix' => ['/cpanel/%postname%/', ['shopper-cms::permalinks.errors.reserved']],
    ]);
})->group('cms');

describe(PermalinkGenerator::class, function (): void {
    it('round-trips every common structure', function (string $structure, string $expected): void {
        useStructure($structure);

        $path = generator()->path($this->post);
        $resolved = resolver()->resolve($path);

        expect($path)->toBe($expected)
            ->and($resolved->type)->toBe('post')
            ->and($resolved->model->is($this->post))->toBeTrue();
    })->with([
        'post name' => ['/%postname%/', '/hello-world/'],
        'day and name' => ['/%year%/%monthnum%/%day%/%postname%/', '/2026/10/05/hello-world/'],
        'month and name' => ['/%year%/%monthnum%/%postname%/', '/2026/10/hello-world/'],
        'numeric' => ['/archives/%post_id%', '/archives/1'],
        'plain' => ['', '/?p=1'],
        'no trailing slash' => ['/blog/%postname%', '/blog/hello-world'],
    ]);

    it('uses the nested primary category for %category%', function (): void {
        useStructure('/%category%/%postname%/');
        $guides = resolve(SavePostCategoryAction::class)->execute(['name' => 'Guides']);
        $audio = resolve(SavePostCategoryAction::class)->execute(['name' => 'Audio', 'parent_id' => $guides->id]);
        resolve(SyncPostTermsAction::class)->execute($this->post, [$audio->id]);

        expect(generator()->path($this->post->refresh()))->toBe('/guides/audio/hello-world/')
            ->and(resolver()->resolve('/guides/audio/hello-world/')->model->is($this->post))->toBeTrue();
    });

    it('builds hierarchical page paths and the front page', function (): void {
        $about = Post::factory()->page()->create(['slug' => 'about']);
        $team = Post::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);
        $home = Post::factory()->page()->create(['slug' => 'home']);
        resolve(PermalinkSettings::class)->save(['front_page_id' => $home->id]);

        expect(generator()->path($team))->toBe('/about/team/')
            ->and(generator()->path($home))->toBe('/')
            ->and(resolver()->resolve('/about/team')->model->is($team))->toBeTrue()
            ->and(resolver()->resolve('/')->model->is($home))->toBeTrue()
            ->and(resolver()->resolve('/team'))->toBeNull();
    });

    it('builds category and tag archive paths with custom bases', function (): void {
        resolve(PermalinkSettings::class)->save(['category_base' => 'topics', 'tag_base' => 'labels']);
        $parent = PostCategory::factory()->create(['name' => 'News']);
        $child = PostCategory::factory()->create(['name' => 'Releases', 'parent_id' => $parent->id]);
        $tag = PostTag::factory()->create(['name' => 'Laravel']);

        expect(generator()->path($child))->toBe('/topics/news/releases/')
            ->and(generator()->path($tag))->toBe('/labels/laravel/')
            ->and(resolver()->resolve('/topics/news/releases/')->model->is($child))->toBeTrue()
            ->and(resolver()->resolve('/labels/laravel/')->model->is($tag))->toBeTrue();
    });

    it('builds absolute URLs from the frontend URL', function (): void {
        config(['shopper.cms.frontend_url' => 'https://shop.example.com/']);

        expect(generator()->url($this->post))->toBe('https://shop.example.com/hello-world/');
    });
})->group('cms');

describe(PermalinkResolver::class, function (): void {
    it('redirects wrong dates and plain links to the canonical path', function (): void {
        useStructure('/%year%/%monthnum%/%postname%/');

        expect(resolver()->resolve('/2025/01/hello-world/'))
            ->isRedirect()->toBeTrue()
            ->redirectTo->toBe('/2026/10/hello-world/')
            ->and(resolver()->resolve('/?p='.$this->post->id)->redirectTo)->toBe('/2026/10/hello-world/');
    });

    it('redirects links of a previous structure', function (): void {
        useStructure('/%year%/%monthnum%/%postname%/');
        useStructure('/blog/%postname%/');

        expect(resolve(PermalinkSettings::class)->history()[0]->value)->toBe('/%year%/%monthnum%/%postname%/')
            ->and(resolver()->resolve('/2026/10/hello-world/'))
            ->isRedirect()->toBeTrue()
            ->redirectTo->toBe('/blog/hello-world/');
    });

    it('prefers a page over a post with the same slug', function (): void {
        $page = Post::factory()->page()->create(['slug' => 'hello-world']);

        expect(resolver()->resolve('/hello-world/')->model->is($page))->toBeTrue();
    });

    it('never resolves reserved paths', function (): void {
        Post::factory()->page()->create(['slug' => 'storage']);

        expect(resolver()->resolve('/storage/'))->toBeNull()
            ->and(resolver()->resolve('/cpanel/posts'))->toBeNull()
            ->and(resolver()->resolve('/does-not-exist/'))->toBeNull();
    });
})->group('cms');
