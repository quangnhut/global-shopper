<?php

declare(strict_types=1);

use Shopper\Cms\Actions\SaveRedirectAction;
use Shopper\Cms\Models\Post;

uses(Tests\Cms\PublicRoutesTestCase::class);

describe('optional public pages', function (): void {
    it('renders published posts and pages', function (): void {
        Post::factory()->published()->create(['title' => 'Hello world', 'slug' => 'hello-world', 'content' => '<p>Body text</p>']);
        Post::factory()->page()->published()->create(['title' => 'About us', 'slug' => 'about']);

        $this->get('/hello-world/')->assertOk()->assertSee('Hello world')->assertSee('<p>Body text</p>', false);
        $this->get('/about/')->assertOk()->assertSee('About us');
    });

    it('returns 404 for drafts and unknown paths, and follows redirects', function (): void {
        Post::factory()->draft()->create(['slug' => 'draft']);
        resolve(SaveRedirectAction::class)->execute('/old', '/new/');

        $this->get('/draft/')->assertNotFound();
        $this->get('/nope/')->assertNotFound();
        $this->get('/old')->assertRedirect('/new/')->assertStatus(301);
    });

    it('asks for the password of protected content', function (): void {
        $post = Post::factory()->published()->create(['slug' => 'members', 'password' => 'open-sesame', 'content' => '<p>Secret</p>']);

        $this->get('/members/')->assertOk()->assertSee(__('shopper-cms::public.protected'))->assertDontSee('Secret');

        $this->post(route('shopper.cms.public.unlock', $post), ['post_password' => 'wrong'])->assertSessionHasErrors('post_password');

        $this->post(route('shopper.cms.public.unlock', $post), ['post_password' => 'open-sesame'])->assertRedirect('/members/');
        $this->get('/members/')->assertSee('Secret');
    });
})->group('cms');
