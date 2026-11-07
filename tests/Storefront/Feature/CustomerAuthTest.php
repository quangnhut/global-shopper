<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Shopper\Storefront\Notifications\StorefrontResetPassword;
use Tests\Core\Stubs\User;

uses(Tests\Storefront\PublicRoutesTestCase::class);

beforeEach(function (): void {
    $this->password = 'Matkhau!2026x';
});

describe('sign up', function (): void {
    it('creates a signed-in customer without admin access', function (): void {
        $this->post(route('shopper.storefront.register.store'), [
            'last_name' => 'Nguyễn',
            'first_name' => 'An',
            'email' => 'an@example.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
        ])->assertRedirect(route('shopper.storefront.home'));

        $user = User::query()->where('email', 'an@example.com')->firstOrFail();

        expect($user->hasRole(config('shopper.admin.roles.user')))->toBeTrue()
            ->and($user->hasRole(config('shopper.admin.roles.admin')))->toBeFalse()
            ->and($user->can('access_dashboard'))->toBeFalse();

        $this->assertAuthenticatedAs($user);

        $this->get(route('shopper.dashboard'))->assertRedirect();
    });

    it('validates the form and rejects a taken email', function (): void {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->from(route('shopper.storefront.register'))
            ->post(route('shopper.storefront.register.store'), [
                'last_name' => '',
                'first_name' => 'An',
                'email' => 'taken@example.com',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('shopper.storefront.register'))
            ->assertSessionHasErrors(['last_name', 'email', 'password']);

        $this->assertGuest();
    });
});

describe('sign in', function (): void {
    it('signs in and out', function (): void {
        $user = User::factory()->create(['email' => 'an@example.com', 'password' => bcrypt($this->password)]);

        $this->post(route('shopper.storefront.login.store'), ['email' => 'an@example.com', 'password' => $this->password])
            ->assertRedirect(route('shopper.storefront.home'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('shopper.storefront.logout'))->assertRedirect(route('shopper.storefront.home'));

        $this->assertGuest();
    });

    it('rejects a wrong password and locks out after five attempts', function (): void {
        User::factory()->create(['email' => 'an@example.com', 'password' => bcrypt($this->password)]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('shopper.storefront.login.store'), ['email' => 'an@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => __('shopper-storefront::auth.failed')]);
        }

        $this->post(route('shopper.storefront.login.store'), ['email' => 'an@example.com', 'password' => $this->password])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('sends signed-in users away from the guest pages', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('shopper.storefront.login'))
            ->assertRedirect();
    });
});

describe('password reset', function (): void {
    it('emails a storefront reset link, without revealing unknown emails', function (): void {
        Notification::fake();
        $user = User::factory()->create(['email' => 'an@example.com']);

        $this->post(route('shopper.storefront.password.email'), ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', __('shopper-storefront::auth.reset_link_sent'));
        $this->post(route('shopper.storefront.password.email'), ['email' => 'an@example.com'])
            ->assertSessionHas('status', __('shopper-storefront::auth.reset_link_sent'));

        Notification::assertSentTo($user, StorefrontResetPassword::class, function (StorefrontResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, route('shopper.storefront.password.reset', $notification->token));
        });
        Notification::assertCount(1);
    });

    it('resets the password with a valid token only', function (): void {
        $user = User::factory()->create(['email' => 'an@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post(route('shopper.storefront.password.update'), [
            'token' => 'invalid',
            'email' => 'an@example.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
        ])->assertSessionHasErrors('email');

        $this->post(route('shopper.storefront.password.update'), [
            'token' => $token,
            'email' => 'an@example.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
        ])->assertRedirect(route('shopper.storefront.login'));

        expect(Hash::check($this->password, $user->refresh()->password))->toBeTrue();
    });
});
