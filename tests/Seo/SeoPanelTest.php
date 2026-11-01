<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\ForceDeletePostAction;
use Shopper\Cms\Livewire\Pages\PostCategories\Index as CategoryIndex;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Livewire\Pages\Posts\Index as PostIndex;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;
use Shopper\Core\Models\Product;
use Shopper\Seo\Livewire\Components\EntrySeoPanel;
use Shopper\Seo\Livewire\Pages\ContentSeo;
use Shopper\Seo\Models\SeoMeta;
use Shopper\Seo\Support\SeoFields;
use Tests\Core\Stubs\User;

uses(Tests\Seo\TestCase::class);

beforeEach(function (): void {
    $this->admin = $this->makeAdminUser();
    $this->actingAs($this->admin);
});

describe(SeoFields::class, function (): void {
    it('stores CMS overrides in `seo_meta` with tri-state robots', function (): void {
        $post = Post::factory()->create();

        SeoFields::save($post, [
            'title' => 'Custom title',
            'description' => 'Custom description',
            'robots_index' => 'no',
            'robots_follow' => 'default',
            'robots_advanced' => ['noarchive'],
            'max_image_preview' => 'large',
            'exclude_from_sitemap' => true,
        ]);

        expect(SeoFields::meta($post))
            ->title->toBe('Custom title')
            ->robots_index->toBeFalse()
            ->robots_follow->toBeNull()
            ->robots_advanced->toBe(['flags' => ['noarchive'], 'max_image_preview' => 'large'])
            ->exclude_from_sitemap->toBeTrue()
            ->and(SeoFields::state($post))
            ->robots_index->toBe('no')
            ->robots_follow->toBe('default')
            ->robots_advanced->toBe(['noarchive']);
    });

    it('keeps shop titles and descriptions in their own columns', function (): void {
        $product = Product::factory()->create(['seo_title' => 'Old']);

        SeoFields::save($product, ['title' => 'Product SEO title', 'description' => 'Product SEO description', 'robots_index' => 'no']);

        expect($product->refresh())
            ->seo_title->toBe('Product SEO title')
            ->seo_description->toBe('Product SEO description')
            ->and(SeoFields::meta($product))->title->toBeNull()->robots_index->toBeFalse()
            ->and(SeoFields::state($product)['title'])->toBe('Product SEO title');
    });

    it('only stores custom JSON-LD for `manage_seo_scripts`', function (): void {
        $post = Post::factory()->create();
        $json = '{"@type":"FAQPage"}';

        SeoFields::save($post, ['schema_custom' => $json]);
        expect(SeoFields::meta($post)->schema_custom)->toBeNull();

        SeoFields::save($post, ['schema_custom' => $json], canEditScripts: true);
        expect(SeoFields::meta($post)->schema_custom)->toBe(['@type' => 'FAQPage']);
    });

    it('drops unsafe canonical URLs', function (): void {
        $post = Post::factory()->create();

        SeoFields::save($post, ['canonical_url' => 'javascript:alert(1)']);

        expect(SeoFields::meta($post)->canonical_url)->toBeNull();
    });

    it('deletes the SEO row with its entry', function (): void {
        $post = Post::factory()->create();
        SeoFields::save($post, ['title' => 'x']);

        resolve(ForceDeletePostAction::class)->execute($post);

        expect(SeoMeta::query()->count())->toBe(0);
    });
})->group('seo');

describe('SEO panel in the CMS', function (): void {
    it('saves the SEO fields with the post', function (): void {
        Livewire::test(Editor::class)
            ->fillForm([
                'title' => 'A post',
                'seo.title' => 'Better title for Google',
                'seo.robots_index' => 'no',
            ])
            ->callAction('saveDraft')
            ->assertHasNoFormErrors();

        expect(SeoFields::meta(Post::query()->firstOrFail()))
            ->title->toBe('Better title for Google')
            ->robots_index->toBeFalse();
    });

    it('validates canonical URLs and custom JSON-LD', function (): void {
        Livewire::test(Editor::class)
            ->fillForm(['title' => 'x', 'seo.canonical_url' => 'javascript:alert(1)', 'seo.schema_custom' => '{not json'])
            ->callAction('saveDraft')
            ->assertHasFormErrors(['seo.canonical_url', 'seo.schema_custom']);
    });

    it('saves SEO from the category dialog', function (): void {
        Livewire::test(CategoryIndex::class)
            ->callAction('create', data: ['name' => 'News', 'seo' => ['title' => 'Latest news', 'robots_index' => 'default']])
            ->assertHasNoActionErrors();

        expect(SeoFields::meta(PostCategory::query()->where('slug', 'news')->firstOrFail())->title)->toBe('Latest news');
    });

    it('sets noindex in bulk from the posts list', function (): void {
        [$first, $second] = Post::factory()->count(2)->create();

        Livewire::test(PostIndex::class)->callTableBulkAction('seo_noindex', [$first, $second]);

        expect(SeoFields::meta($first)->robots_index)->toBeFalse()
            ->and(SeoFields::meta($second)->robots_index)->toBeFalse();
    });
})->group('seo', 'livewire');

describe(ContentSeo::class, function (): void {
    it('lists entries per type and edits their SEO', function (): void {
        $product = Product::factory()->create(['name' => 'Laptop']);

        Livewire::test(ContentSeo::class)
            ->set('activeTab', 'product')
            ->assertCanSeeTableRecords([$product])
            ->callTableAction('editSeo', $product, data: ['seo' => ['title' => 'Buy the laptop', 'robots_index' => 'default', 'robots_follow' => 'default']])
            ->assertHasNoTableActionErrors();

        expect($product->refresh()->seo_title)->toBe('Buy the laptop');
    });

    it('shows only posts or only pages in their tabs', function (): void {
        $post = Post::factory()->create();
        $page = Post::factory()->page()->create();

        Livewire::test(ContentSeo::class)
            ->set('activeTab', 'page')
            ->assertCanSeeTableRecords([$page])
            ->assertCanNotSeeTableRecords([$post]);
    });

    it('removes entries from the sitemap in bulk', function (): void {
        $products = Product::factory()->count(2)->create();

        Livewire::test(ContentSeo::class)
            ->set('activeTab', 'product')
            ->callTableBulkAction('seo_exclude_sitemap', $products);

        expect(SeoMeta::query()->where('exclude_from_sitemap', true)->count())->toBe(2);
    });

    it('requires `manage_seo`', function (): void {
        $this->configureStore();
        $user = User::factory()->create();
        $user->givePermissionTo('access_dashboard');

        $this->actingAs($user)->get(route('shopper.seo.content'))->assertRedirect(route('shopper.forbidden'));
    });
})->group('seo', 'livewire');

describe(EntrySeoPanel::class, function (): void {
    it('edits advanced SEO of a product without touching its title', function (): void {
        $product = Product::factory()->create(['seo_title' => 'Kept']);

        Livewire::test(EntrySeoPanel::class, ['type' => 'product', 'recordId' => $product->id, 'withBasics' => false])
            ->fillForm(['seo.og_title' => 'Share me', 'seo.robots_index' => 'no'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->refresh()->seo_title)->toBe('Kept')
            ->and(SeoFields::meta($product))->og_title->toBe('Share me')->robots_index->toBeFalse();
    });

    it('adds an "Advanced SEO" tab to the product edit page', function (): void {
        $this->configureStore();
        setupCurrencies();
        $product = Product::factory()->create();

        $this->get(route('shopper.products.edit', $product))
            ->assertOk()
            ->assertSee(__('shopper-seo::panel.advanced_tab'))
            ->assertSeeLivewire(EntrySeoPanel::class);
    });
})->group('seo', 'livewire');
