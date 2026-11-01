<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Models\Contracts\Post as PostContract;
use Shopper\Cms\Models\Post;
use Shopper\Core\Models\Upload;

uses(Tests\Cms\TestCase::class);

describe(Post::class, function (): void {
    it('resolves the `Post` contract to the configured model', function (): void {
        expect(resolve(PostContract::class))->toBeInstanceOf(Post::class);
    });

    it('casts its attributes', function (): void {
        $post = Post::factory()->create(['metadata' => ['color' => 'blue']]);

        expect($post->refresh())
            ->status->toBe(PostStatus::Published)
            ->published_at->toBeInstanceOf(Carbon\CarbonInterface::class)
            ->metadata->toBe(['color' => 'blue']);
    });

    it('hashes the password and hides it from serialization', function (): void {
        $post = Post::factory()->create(['password' => 'secret']);

        expect(Hash::check('secret', $post->password))->toBeTrue()
            ->and($post->toArray())->not->toHaveKey('password')
            ->and($post->visibility())->toBe(PostVisibility::Password);
    });

    it('derives the visibility like WordPress', function (): void {
        expect(Post::factory()->private()->make()->visibility())->toBe(PostVisibility::Private)
            ->and(Post::factory()->make()->visibility())->toBe(PostVisibility::Public);
    });

    it('only returns live content from the `published()` scope', function (): void {
        $live = Post::factory()->published()->create();
        $protected = Post::factory()->published()->create(['password' => 'secret']);
        Post::factory()->draft()->create();
        Post::factory()->pending()->create();
        Post::factory()->scheduled()->create();
        Post::factory()->private()->create();
        Post::factory()->published()->trashed()->create();
        Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()->addDay()]);

        expect(Post::query()->published()->pluck('id')->all())
            ->toEqualCanonicalizing([$live->id, $protected->id]);
    });

    it('filters by type with the `ofType()` scope', function (): void {
        Post::factory()->count(2)->create();
        $page = Post::factory()->page()->create();

        expect(Post::query()->ofType('page')->pluck('id')->all())->toBe([$page->id]);
    });

    it('orders page children by `menu_order`', function (): void {
        $parent = Post::factory()->page()->create();
        $second = Post::factory()->page()->create(['parent_id' => $parent->id, 'menu_order' => 2]);
        $first = Post::factory()->page()->create(['parent_id' => $parent->id, 'menu_order' => 1]);

        expect($parent->children->pluck('id')->all())->toBe([$first->id, $second->id])
            ->and($first->parent->is($parent))->toBeTrue();
    });

    it('links a featured image from the uploads library', function (): void {
        $upload = Upload::query()->create([
            'name' => 'cover.png', 'file_name' => 'cover.png', 'path' => 'uploads/cover.png',
            'disk' => 'public', 'mime_type' => 'image/png', 'extension' => 'png', 'size' => 10,
        ]);

        $post = Post::factory()->create(['featured_upload_id' => $upload->id]);

        expect($post->featuredImage->is($upload))->toBeTrue();
    });

    it('returns its post type definition', function (): void {
        expect(Post::factory()->page()->make()->postType())
            ->key->toBe('page')
            ->hierarchical->toBeTrue();
    });
})->group('cms');
