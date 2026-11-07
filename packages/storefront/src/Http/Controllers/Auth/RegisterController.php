<?php

declare(strict_types=1);

namespace Shopper\Storefront\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Shopper\Cart\CartSessionManager;
use Shopper\Storefront\Support\StorefrontCache;

/**
 * Customer sign-up: the account gets the customer role (`shopper.admin.roles.user`) and no admin access.
 */
final class RegisterController
{
    public function create(): View
    {
        return view('shopper-storefront::auth.register', StorefrontCache::layout());
    }

    public function store(Request $request, CartSessionManager $carts): RedirectResponse
    {
        $userModel = (string) config('auth.providers.users.model');

        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique((new $userModel)->getTable(), 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $userModel::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole(config('shopper.admin.roles.user', 'user'));
        }

        event(new Registered($user));

        Auth::guard(LoginController::guard())->login($user);
        $request->session()->regenerate();

        if ($carts->current() !== null) {
            $carts->associate($user);
        }

        return redirect()
            ->route('shopper.storefront.home')
            ->with('status', __('shopper-storefront::auth.registered'));
    }
}
