<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Actions\GeneratePreviewUrlAction;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\TestCase::class);

describe(GeneratePreviewUrlAction::class, function (): void {
    it('shows a draft through a signed link without logging in', function (): void {
        $post = Post::factory()->draft()->create(['title' => 'Secret draft', 'content' => '<p>Draft body</p>']);

        $this->get(resolve(GeneratePreviewUrlAction::class)->execute($post))
            ->assertOk()
            ->assertSee('Secret draft')
            ->assertSee('noindex, nofollow', false);
    });

    it('renders the content in a sandbox without scripts', function (): void {
        $post = Post::factory()->draft()->create(['content' => '<script>alert(1)</script>']);

        $this->get(resolve(GeneratePreviewUrlAction::class)->execute($post))
            ->assertOk()
            ->assertSee('<iframe sandbox srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    });

    it('rejects links without a valid signature', function (): void {
        $post = Post::factory()->draft()->create();

        $this->get(route('shopper.cms.preview', $post->id))->assertForbidden();
        $this->get(resolve(GeneratePreviewUrlAction::class)->execute($post).'x')->assertForbidden();
    });

    it('expires after `preview_minutes`', function (): void {
        $post = Post::factory()->draft()->create();
        $url = resolve(GeneratePreviewUrlAction::class)->execute($post);

        $this->travel(31)->minutes();

        $this->get($url)->assertForbidden();
    });

    it('adds a preview button to saved content only', function (): void {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(Editor::class)->assertActionHidden('preview');
        Livewire::test(Editor::class, ['post' => Post::factory()->draft()->create()])->assertActionVisible('preview');
    });
})->group('cms');
