<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shopper\Storefront\Actions\MergeCartOnLoginAction;
use Shopper\Storefront\Support\StorefrontCache;

final class LoginController
{
    private const int MAX_ATTEMPTS = 5;

    public static function guard(): string
    {
        return (string) config('shopper.auth.guard', 'web');
    }

    public function create(): View
    {
        return view('shopper-storefront::auth.login', StorefrontCache::layout());
    }

    public function store(Request $request, MergeCartOnLoginAction $mergeCart): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('shopper-storefront::auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (! Auth::guard(self::guard())->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('shopper-storefront::auth.failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $mergeCart->execute(Auth::guard(self::guard())->user());

        return redirect()->intended(route('shopper.storefront.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard(self::guard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shopper.storefront.home');
    }
}
