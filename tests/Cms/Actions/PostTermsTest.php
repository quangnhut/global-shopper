<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Shopper\Cms\Actions\DeletePostCategoryAction;
use Shopper\Cms\Actions\ResolveDefaultCategoryAction;
use Shopper\Cms\Actions\SavePostCategoryAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Cms\Models\PostTag;
use Shopper\Core\Models\Setting;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->sync = resolve(SyncPostTermsAction::class);
    $this->default = resolve(ResolveDefaultCategoryAction::class)->execute();
});

describe(ResolveDefaultCategoryAction::class, function (): void {
    it('creates "Uncategorized" with the migrations', function (): void {
        expect($this->default)
            ->slug->toBe('uncategorized')
            ->name->toBe('Uncategorized')
            ->and(Setting::query()->where('key', 'cms_default_category_id')->value('value'))->toBe($this->default->id);
    });

    it('recreates the default category when it is missing', function (): void {
        Setting::query()->where('key', 'cms_default_category_id')->delete();
        $this->default->delete();

        $default = resolve(ResolveDefaultCategoryAction::class)->execute();

        expect($default->exists)->toBeTrue()
            ->and($default->id)->not->toBe($this->default->id);
    });
})->group('cms');

describe(SyncPostTermsAction::class, function (): void {
    it('puts a post without categories in the default category', function (): void {
        $post = $this->sync->execute(Post::factory()->create());

        expect($post->categories->pluck('id')->all())->toBe([$this->default->id])
            ->and($post->primary_category_id)->toBe($this->default->id);
    });

    it('syncs categories and keeps the requested primary category', function (): void {
        [$news, $guides] = PostCategory::factory()->count(2)->create();

        $post = $this->sync->execute(Post::factory()->create(), [$news->id, (string) $guides->id], primaryCategoryId: $guides->id);

        expect($post->categories->pluck('id')->all())->toEqualCanonicalizing([$news->id, $guides->id])
            ->and($post->primary_category_id)->toBe($guides->id);
    });

    it('falls back to the first category when the primary one is removed', function (): void {
        [$news, $guides] = PostCategory::factory()->count(2)->create();
        $post = $this->sync->execute(Post::factory()->create(), [$news->id, $guides->id], primaryCategoryId: $guides->id);

        expect($this->sync->execute($post, [$news->id])->primary_category_id)->toBe($news->id);
    });

    it('creates new tags from names and reuses existing ones', function (): void {
        $existing = PostTag::factory()->create(['name' => 'Laravel']);

        $post = $this->sync->execute(Post::factory()->create(), tags: ['laravel', 'Open Source', ' ', $existing->id]);

        expect($post->tags->pluck('name')->all())->toEqualCanonicalizing(['Laravel', 'Open Source'])
            ->and(PostTag::query()->where('slug', 'open-source')->exists())->toBeTrue()
            ->and(PostTag::query()->count())->toBe(2);
    });

    it('ignores taxonomies on types that do not support them', function (): void {
        $page = $this->sync->execute(Post::factory()->page()->create(), [$this->default->id], ['News']);

        expect($page->categories)->toBeEmpty()
            ->and($page->tags)->toBeEmpty()
            ->and(PostTag::query()->count())->toBe(0);
    });
})->group('cms');

describe(DeletePostCategoryAction::class, function (): void {
    it('moves orphaned posts to the default category and children up a level', function (): void {
        $parent = PostCategory::factory()->create();
        $category = PostCategory::factory()->create(['parent_id' => $parent->id]);
        $child = PostCategory::factory()->create(['parent_id' => $category->id]);
        $other = PostCategory::factory()->create();

        $onlyHere = $this->sync->execute(Post::factory()->create(), [$category->id]);
        $alsoElsewhere = $this->sync->execute(Post::factory()->create(), [$category->id, $other->id], primaryCategoryId: $category->id);

        resolve(DeletePostCategoryAction::class)->execute($category);

        expect(PostCategory::query()->find($category->id))->toBeNull()
            ->and($child->refresh()->parent_id)->toBe($parent->id)
            ->and($onlyHere->categories()->pluck('id')->all())->toBe([$this->default->id])
            ->and($onlyHere->refresh()->primary_category_id)->toBe($this->default->id)
            ->and($alsoElsewhere->categories()->pluck('id')->all())->toBe([$other->id])
            ->and($alsoElsewhere->refresh()->primary_category_id)->toBe($other->id);
    });

    it('refuses to delete the default category', function (): void {
        resolve(DeletePostCategoryAction::class)->execute($this->default);
    })->throws(ValidationException::class);
})->group('cms');

describe(SavePostCategoryAction::class, function (): void {
    it('refuses to move a category under one of its descendants', function (): void {
        $category = PostCategory::factory()->create();
        $grandchild = PostCategory::factory()->create([
            'parent_id' => PostCategory::factory()->create(['parent_id' => $category->id])->id,
        ]);

        resolve(SavePostCategoryAction::class)->execute(['parent_id' => $grandchild->id], $category);
    })->throws(ValidationException::class);

    it('generates the slug when it is left empty', function (): void {
        expect(resolve(SavePostCategoryAction::class)->execute(['name' => 'Product News', 'slug' => ''])->slug)
            ->toBe('product-news');
    });
})->group('cms');

describe(PostCategory::class, function (): void {
    it('generates a unique slug from the name', function (): void {
        PostCategory::factory()->create(['name' => 'News']);

        expect(PostCategory::factory()->create(['name' => 'News'])->slug)->toBe('news-1');
    });
})->group('cms');
