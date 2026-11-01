<?php

declare(strict_types=1);

use Shopper\Cms\Actions\GeneratePreviewUrlAction;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\SaveRedirectAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;

uses(Tests\Cms\TestCase::class);

function resolvePath(string $path, array $headers = []): Illuminate\Testing\TestResponse
{
    return test()->getJson(route('shopper.cms.api.resolve', ['path' => $path]), $headers);
}

beforeEach(function (): void {
    $this->post = Post::factory()->published()->create(['title' => 'Hello world', 'slug' => 'hello-world', 'content' => '<p>Body</p>']);
});

describe('GET /api/cms/resolve', function (): void {
    it('returns published content with its URL', function (): void {
        resolvePath('/hello-world/')
            ->assertOk()
            ->assertJsonPath('type', 'post')
            ->assertJsonPath('data.title', 'Hello world')
            ->assertJsonPath('data.content', '<p>Body</p>')
            ->assertJsonPath('data.path', '/hello-world/')
            ->assertJsonPath('data.url', mb_rtrim(config('app.url'), '/').'/hello-world/');
    });

    it('hides content that is not public', function (string $state): void {
        Post::factory()->{$state}()->create(['slug' => 'hidden']);

        resolvePath('/hidden/')->assertNotFound()->assertJsonPath('type', 'not_found');
    })->with(['draft', 'pending', 'scheduled', 'private', 'trashed']);

    it('locks password-protected content until the password is sent', function (): void {
        Post::factory()->published()->create(['slug' => 'members', 'password' => 'open-sesame', 'content' => '<p>Secret</p>']);

        resolvePath('/members/')->assertOk()->assertJsonPath('data.protected', true)->assertJsonPath('data.content', null);
        resolvePath('/members/', ['X-Post-Password' => 'wrong'])->assertJsonPath('data.content', null);
        resolvePath('/members/', ['X-Post-Password' => 'open-sesame'])->assertJsonPath('data.content', '<p>Secret</p>');
    });

    it('returns redirects and gone paths as JSON without following them', function (): void {
        resolve(SaveRedirectAction::class)->execute('/old-hello', '/hello-world/');
        resolve(SaveRedirectAction::class)->execute('/removed', null, 410);

        resolvePath('/old-hello')
            ->assertStatus(301)
            ->assertHeaderMissing('Location')
            ->assertJson(['type' => 'redirect', 'redirect' => '/hello-world/', 'status' => 301]);

        resolvePath('/removed')->assertStatus(410)->assertJsonPath('type', 'gone');
        resolvePath('/nothing-here')->assertNotFound();
    });

    it('lists the published posts of a category archive', function (): void {
        $category = PostCategory::factory()->create(['name' => 'News']);
        resolve(SyncPostTermsAction::class)->execute($this->post, [$category->id]);
        resolve(SyncPostTermsAction::class)->execute(Post::factory()->draft()->create(), [$category->id]);

        resolvePath('/category/news/')
            ->assertOk()
            ->assertJsonPath('type', 'category')
            ->assertJsonPath('data.name', 'News')
            ->assertJsonCount(1, 'posts.data')
            ->assertJsonPath('posts.data.0.slug', 'hello-world');
    });

    it('serves fresh content after an update', function (): void {
        resolvePath('/hello-world/')->assertJsonPath('data.title', 'Hello world');

        resolve(SavePostAction::class)->execute(['title' => 'Hello again'], $this->post);

        resolvePath('/hello-world/')->assertJsonPath('data.title', 'Hello again');
    });
})->group('cms', 'api');

describe('optional public pages', function (): void {
    it('are not registered unless `shopper.cms.routes.enabled` is on', function (): void {
        expect(Illuminate\Support\Facades\Route::has('shopper.cms.public'))->toBeFalse();
    });
})->group('cms');

describe('GET /api/cms/posts', function (): void {
    it('filters published posts by category, tag and search', function (): void {
        $news = PostCategory::factory()->create(['name' => 'News']);
        $tag = PostTag::factory()->create(['name' => 'Laravel']);
        $other = Post::factory()->published()->create(['title' => 'Another story']);
        resolve(SyncPostTermsAction::class)->execute($this->post, [$news->id], [$tag->id]);
        Post::factory()->draft()->create(['title' => 'Hello draft']);

        $this->getJson(route('shopper.cms.api.posts'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('shopper.cms.api.posts', ['category' => 'news']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->post->id);
        $this->getJson(route('shopper.cms.api.posts', ['tag' => 'laravel']))->assertJsonCount(1, 'data');
        $this->getJson(route('shopper.cms.api.posts', ['search' => 'Another']))->assertJsonPath('data.0.id', $other->id);
        $this->getJson(route('shopper.cms.api.posts', ['search' => 'draft']))->assertJsonCount(0, 'data');
    });
})->group('cms', 'api');

describe('GET /api/cms/preview/{post}', function (): void {
    it('shows drafts through a signed link only', function (): void {
        $draft = Post::factory()->draft()->create(['title' => 'Draft title']);

        $this->getJson(resolve(GeneratePreviewUrlAction::class)->api($draft))
            ->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('data.title', 'Draft title');

        $this->getJson(route('shopper.cms.api.preview', $draft))->assertForbidden();
    });
})->group('cms', 'api');
