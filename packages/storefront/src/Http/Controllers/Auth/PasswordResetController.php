<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Shopper\Storefront\Notifications\StorefrontResetPassword;
use Shopper\Storefront\Support\StorefrontCache;

final class PasswordResetController
{
    public function request(): View
    {
        return view('shopper-storefront::auth.forgot-password', StorefrontCache::layout());
    }

    /**
     * Always answers with the same message, so the form does not reveal which emails have an account.
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        Password::broker()->sendResetLink(
            $request->only('email'),
            function (CanResetPassword $user, string $token): void {
                if (method_exists($user, 'notify')) {
                    $user->notify(new StorefrontResetPassword($token));
                }
            },
        );

        return back()->with('status', __('shopper-storefront::auth.reset_link_sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('shopper-storefront::auth.reset-password', [
            ...StorefrontCache::layout(),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Authenticatable&Model $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('shopper-storefront::auth.reset_failed')]);
        }

        return redirect()
            ->route('shopper.storefront.login')
            ->with('status', __('shopper-storefront::auth.password_reset'));
    }
}
