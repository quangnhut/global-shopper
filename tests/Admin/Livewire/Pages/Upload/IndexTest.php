<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Shopper\Core\Actions\StoreUploadAction;
use Shopper\Core\Models\Upload;
use Shopper\Livewire\Pages\Upload\Index;
use Tests\Core\Stubs\User;

uses(Tests\Admin\TestCase::class);

beforeEach(function (): void {
    Storage::fake(config('shopper.media.uploads.disk'));

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('browse_uploads');
    $this->actingAs($this->user);
});

describe(Index::class, function (): void {
    it('hides the upload action for users without `add_uploads`', function (): void {
        Livewire::test(Index::class)
            ->assertActionHidden('upload');
    });

    it('uploads allowed files into the dated uploads folder', function (): void {
        $this->user->givePermissionTo('add_uploads');

        Livewire::test(Index::class)
            ->callAction('upload', data: [
                'files' => [
                    UploadedFile::fake()->image('Summer Banner.png'),
                    UploadedFile::fake()->createWithContent('notes.txt', 'Hello world'),
                ],
            ])
            ->assertHasNoActionErrors();

        $directory = 'uploads/'.now()->format('Y/m');

        expect(Upload::query()->count())->toBe(2)
            ->and(Upload::query()->where('file_name', 'summer-banner.png')->first())
            ->path->toBe("{$directory}/summer-banner.png")
            ->user_id->toBe($this->user->id);

        Storage::disk(config('shopper.media.uploads.disk'))
            ->assertExists("{$directory}/summer-banner.png")
            ->assertExists("{$directory}/notes.txt");
    });

    it('removes the file from disk when an upload is deleted', function (): void {
        $this->user->givePermissionTo('delete_uploads');

        $upload = resolve(StoreUploadAction::class)->execute(UploadedFile::fake()->image('logo.webp'));

        Livewire::test(Index::class)
            ->callTableAction('delete', $upload);

        expect(Upload::query()->count())->toBe(0);

        Storage::disk($upload->disk)->assertMissing($upload->path);
    });
})->group('livewire', 'uploads');

describe(StoreUploadAction::class, function (): void {
    it('rejects a script renamed with an allowed extension', function (): void {
        // A real file is needed here: fake files report a mime type guessed from their name.
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php echo "pwned";');

        resolve(StoreUploadAction::class)->execute(new UploadedFile($path, 'shell.png', test: true));
    })->throws(InvalidArgumentException::class);

    it('never overwrites an existing file with the same name', function (): void {
        $action = resolve(StoreUploadAction::class);

        $first = $action->execute(UploadedFile::fake()->image('photo.jpg'));
        $second = $action->execute(UploadedFile::fake()->image('photo.jpg'));

        expect($first->file_name)->toBe('photo.jpg')
            ->and($second->file_name)->toBe('photo-1.jpg');
    });
})->group('uploads', 'security');
