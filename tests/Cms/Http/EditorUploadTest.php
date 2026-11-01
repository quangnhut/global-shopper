<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Shopper\Cms\Forms\Components\TinyEditor;
use Shopper\Cms\Livewire\Pages\Posts\Editor;
use Shopper\Core\Models\Upload;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    Storage::fake(config('shopper.media.uploads.disk'));
    $this->configureStore();
});

function editorUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['access_dashboard', ...$permissions]);

    return $user;
}

describe('editor image uploads', function (): void {
    it('stores an image in the uploads library and returns its location', function (): void {
        $this->actingAs(editorUser(['add_uploads']))
            ->postJson(route('shopper.cms.editor.upload'), ['file' => UploadedFile::fake()->image('pasted.png')])
            ->assertOk()
            ->assertJsonPath('location', Upload::query()->firstOrFail()->url);
    });

    it('requires `add_uploads`', function (): void {
        $this->actingAs(editorUser([]))
            ->postJson(route('shopper.cms.editor.upload'), ['file' => UploadedFile::fake()->image('pasted.png')])
            ->assertForbidden();

        expect(Upload::query()->count())->toBe(0);
    });

    it('only accepts images', function (): void {
        $this->actingAs(editorUser(['add_uploads']))
            ->postJson(route('shopper.cms.editor.upload'), ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
            ->assertUnprocessable();
    });

    it('rejects guests', function (): void {
        $this->postJson(route('shopper.cms.editor.upload'), ['file' => UploadedFile::fake()->image('pasted.png')])
            ->assertUnauthorized();
    });

    it('turns on TinyMCE uploads only for users who may upload', function (): void {
        $this->actingAs(editorUser(['browse_posts', 'add_posts']));
        Livewire::test(Editor::class)->assertDontSeeHtml('cms\/editor\/upload');

        $this->actingAs(editorUser(['browse_posts', 'add_posts', 'add_uploads']));
        Livewire::test(Editor::class)->assertSeeHtml('cms\/editor\/upload');
    });
})->group('cms');

describe('"Add media"', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->makeAdminUser());
    });

    function upload(string $name, string $mime): Upload
    {
        return Upload::query()->create([
            'name' => $name, 'file_name' => $name, 'path' => "uploads/{$name}", 'disk' => 'public',
            'mime_type' => $mime, 'extension' => pathinfo($name, PATHINFO_EXTENSION), 'size' => 10,
        ]);
    }

    it('inserts an aligned image with escaped alt text into the requesting editor', function (): void {
        $image = upload('cover.png', 'image/png');

        Livewire::test(Editor::class)
            ->callAction('insertMedia', data: ['upload_id' => $image->id, 'alt' => 'A "quoted" <alt>', 'align' => 'right'], arguments: ['statePath' => 'data.content'])
            ->assertDispatched('cms-editor-insert', fn (string $event, array $params): bool => $params['statePath'] === 'data.content'
                && $params['html'] === sprintf('<img src="%s" alt="A &quot;quoted&quot; &lt;alt&gt;" class="alignright">', e($image->url)));
    });

    it('links other files', function (): void {
        $archive = upload('catalog.zip', 'application/zip');

        Livewire::test(Editor::class)
            ->callAction('insertMedia', data: ['upload_id' => $archive->id], arguments: ['statePath' => 'data.content'])
            ->assertDispatched('cms-editor-insert', fn (string $event, array $params): bool => $params['html'] === sprintf('<a href="%s">catalog.zip</a>', e($archive->url)));
    });

    it('shows the button only when an action is wired', function (): void {
        expect(TinyEditor::make('content')->getEditorConfig()['toolbar'])->not->toContain('addmedia')
            ->and(TinyEditor::make('content')->mediaAction('insertMedia')->getEditorConfig()['toolbar'])->toContain('addmedia');
    });
})->group('cms');
