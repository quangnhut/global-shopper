<?php

declare(strict_types=1);

use Livewire\Livewire;
use Shopper\Cms\Livewire\Components\DashboardOverview;
use Shopper\Cms\Models\Post;
use Shopper\View\LayoutRenderHook;
use Tests\Core\Stubs\User;

uses(Tests\Cms\TestCase::class);

describe(DashboardOverview::class, function (): void {
    it('is added to the end of the dashboard', function (): void {
        expect(shopper()->getRenderHook(LayoutRenderHook::DASHBOARD_END)->toHtml())
            ->toContain('wire:snapshot');
    });

    it('counts published, draft and pending content per type', function (): void {
        $this->actingAs($this->makeAdminUser());
        Post::factory()->count(3)->published()->create();
        Post::factory()->draft()->create();
        Post::factory()->pending()->create();
        Post::factory()->page()->published()->create();

        $glance = collect(Livewire::test(DashboardOverview::class)->instance()->glance)
            ->mapWithKeys(fn (array $row): array => [$row['type']->key => [$row['published'], $row['drafts'], $row['pending']]]);

        expect($glance->all())->toBe(['post' => [3, 1, 1], 'page' => [1, 0, 0]]);
    });

    it('lists only the current user drafts', function (): void {
        $admin = $this->makeAdminUser();
        $mine = Post::factory()->draft()->create(['author_id' => $admin->id, 'title' => 'My draft']);
        Post::factory()->draft()->create(['title' => 'Someone else draft']);

        $this->actingAs($admin);

        Livewire::test(DashboardOverview::class)
            ->assertSee('My draft')
            ->assertDontSee('Someone else draft');
    });

    it('shows nothing to users who cannot browse content', function (): void {
        $this->actingAs(User::factory()->create());
        Post::factory()->published()->create();

        expect(Livewire::test(DashboardOverview::class)->instance()->glance)->toBe([]);
    });
})->group('cms', 'livewire');
