<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\SavePostCategoryAction;
use Shopper\Cms\Actions\SaveRedirectAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Livewire\Pages\Settings\Redirects;
use Shopper\Cms\Models\Redirect;
use Shopper\Cms\Permalinks\PermalinkResolver;
use Shopper\Cms\Permalinks\PermalinkSettings;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->save = resolve(SavePostAction::class);
    $this->redirects = resolve(SaveRedirectAction::class);
});

describe('automatic redirects', function (): void {
    it('redirects the old slug of a published post to the new one', function (): void {
        $post = $this->save->execute(['title' => 'Hello world', 'status' => 'publish']);

        $this->save->execute(['slug' => 'hello-everyone'], $post);

        expect(resolve(PermalinkResolver::class)->resolve('/hello-world/'))
            ->isRedirect()->toBeTrue()
            ->redirectTo->toBe('/hello-everyone/')
            ->and(Redirect::query()->first()->source)->toBe(Redirect::SOURCE_AUTO);
    });

    it('ignores drafts, whose URLs were never public', function (): void {
        $post = $this->save->execute(['title' => 'Draft']);

        $this->save->execute(['slug' => 'renamed-draft'], $post);

        expect(Redirect::query()->count())->toBe(0);
    });

    it('collapses chains and drops the redirect when a URL comes back', function (): void {
        $post = $this->save->execute(['title' => 'A', 'slug' => 'a', 'status' => 'publish']);
        $this->save->execute(['slug' => 'b'], $post);
        $this->save->execute(['slug' => 'c'], $post);

        expect(Redirect::query()->pluck('to_path', 'from_path')->all())->toBe(['/a' => '/c/', '/b' => '/c/']);

        $this->save->execute(['slug' => 'a'], $post);

        expect(Redirect::query()->where('from_path', '/a')->exists())->toBeFalse()
            ->and(Redirect::query()->pluck('to_path', 'from_path')->all())->toBe(['/b' => '/a/', '/c' => '/a/']);
    });

    it('redirects child pages when a parent page moves', function (): void {
        $about = $this->save->execute(['type' => 'page', 'title' => 'About', 'status' => 'publish']);
        $team = $this->save->execute(['type' => 'page', 'title' => 'Team', 'parent_id' => $about->id, 'status' => 'publish']);

        $this->save->execute(['slug' => 'company'], $about);

        expect(resolve(PermalinkResolver::class)->resolve('/about/team/')->redirectTo)->toBe('/company/team/')
            ->and(resolve(PermalinkResolver::class)->resolve('/company/team/')->model->is($team))->toBeTrue();
    });

    it('redirects when the primary category in a %category% URL changes', function (): void {
        resolve(PermalinkSettings::class)->save(['structure' => '/%category%/%postname%/']);
        $news = resolve(SavePostCategoryAction::class)->execute(['name' => 'News']);
        $deals = resolve(SavePostCategoryAction::class)->execute(['name' => 'Deals']);
        $post = $this->save->execute(['title' => 'Sale', 'status' => 'publish']);
        resolve(SyncPostTermsAction::class)->execute($post, [$news->id]);

        resolve(SyncPostTermsAction::class)->execute($post->refresh(), [$deals->id]);

        expect(resolve(PermalinkResolver::class)->resolve('/news/sale/')->redirectTo)->toBe('/deals/sale/');
    });
})->group('cms');

describe(SaveRedirectAction::class, function (): void {
    it('normalizes paths', function (): void {
        $redirect = $this->redirects->execute('/Old-Page/?utm=x', 'new-page/');

        expect($redirect)->from_path->toBe('/old-page')->to_path->toBe('/new-page/');
    });

    it('rejects loops and self redirects', function (string $from, string $to): void {
        $this->redirects->execute('/a', '/b');

        $this->redirects->execute($from, $to);
    })->with([
        'loop' => ['/b', '/a'],
        'self' => ['/x', '/x/'],
    ])->throws(ValidationException::class);

    it('marks a path as gone and counts hits', function (): void {
        $this->redirects->execute('/discontinued', null, 410);

        $resolved = resolve(PermalinkResolver::class)->resolve('/discontinued');
        resolve(PermalinkResolver::class)->resolve('/discontinued/');

        expect($resolved)->type->toBe('gone')->status->toBe(410)
            ->and(Redirect::query()->first())->hits->toBe(2)->last_hit_at->not->toBeNull();
    });

    it('keeps external targets as they are', function (): void {
        expect($this->redirects->execute('/old-shop', 'https://shop.example.com/sale')->to_path)
            ->toBe('https://shop.example.com/sale');
    });
})->group('cms');

describe(Redirects::class, function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->makeAdminUser());
    });

    it('creates, edits and deletes manual redirects', function (): void {
        Livewire::test(Redirects::class)
            ->callAction('create', data: ['from_path' => '/summer', 'to_path' => '/sale', 'status_code' => 302])
            ->assertHasNoActionErrors();

        $redirect = Redirect::query()->firstOrFail();
        expect($redirect)->status_code->toBe(302)->source->toBe(Redirect::SOURCE_MANUAL);

        Livewire::test(Redirects::class)
            ->assertCanSeeTableRecords([$redirect])
            ->callTableAction('edit', $redirect, data: ['from_path' => '/summer', 'to_path' => '/winter-sale', 'status_code' => 301])
            ->callTableAction('delete', $redirect);

        expect(Redirect::query()->count())->toBe(0);
    });

    it('shows loop errors on the form', function (): void {
        resolve(SaveRedirectAction::class)->execute('/a', '/b');

        Livewire::test(Redirects::class)
            ->callAction('create', data: ['from_path' => '/b', 'to_path' => '/a', 'status_code' => 301])
            ->assertHasActionErrors(['to_path']);
    });
})->group('cms', 'livewire');
