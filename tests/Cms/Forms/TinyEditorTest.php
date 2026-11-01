<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Forms\Components\TinyEditor;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\TestCase::class);

describe(TinyEditor::class, function (): void {
    it('builds the TinyMCE config from `shopper.cms.editor`', function (): void {
        $config = TinyEditor::make('content')->getEditorConfig();

        expect($config)
            ->license_key->toBe('gpl')
            ->menubar->toBeFalse()
            ->height->toBe(500)
            ->automatic_uploads->toBeFalse()
            ->and($config['plugins'])->toContain('lists', 'link', 'image', 'table');
    });

    it('lets each field override the toolbar, plugins and height', function (): void {
        $config = TinyEditor::make('content')
            ->toolbar('bold italic')
            ->plugins('lists')
            ->height(200)
            ->getEditorConfig();

        expect($config)
            ->toolbar->toBe('bold italic')
            ->plugins->toBe('lists')
            ->height->toBe(200);
    });

    it('points to the published TinyMCE folder', function (): void {
        expect(TinyEditor::make('content')->getBaseUrl())->toEndWith('/vendor/shopper-cms/tinymce');
    });

    it('renders as the content field of the post editor', function (): void {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(Editor::class)
            ->assertSeeHtml('x-data="tinyEditor(')
            ->assertSeeHtml('js/shopper/cms/components/tiny-editor.js')
            ->assertSeeHtml('license_key');
    });

    it('saves the HTML held by the editor', function (): void {
        $this->actingAs($this->makeAdminUser());

        Livewire::test(Editor::class)
            ->fillForm(['title' => 'Rich', 'content' => '<h2>Heading</h2><ul><li>One</li></ul>'])
            ->callAction('saveDraft');

        expect(Post::query()->firstOrFail()->content)->toBe('<h2>Heading</h2><ul><li>One</li></ul>');
    });
})->group('cms');
