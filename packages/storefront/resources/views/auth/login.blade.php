@extends('shopper-storefront::layout', ['seoContext' => []])

@section('content')
    <x-shopper-storefront::auth-card :title="__('shopper-storefront::auth.login.title')" :subtitle="__('shopper-storefront::auth.login.subtitle')">
        <form method="POST" action="{{ route('shopper.storefront.login.store') }}" class="space-y-4">
            @csrf
            <x-shopper-storefront::field name="email" type="email" :label="__('shopper-storefront::auth.fields.email')" autocomplete="email" autofocus />
            <x-shopper-storefront::field name="password" type="password" :label="__('shopper-storefront::auth.fields.password')" autocomplete="current-password" />

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('remember')) />
                    {{ __('shopper-storefront::auth.fields.remember') }}
                </label>
                <a href="{{ route('shopper.storefront.password.request') }}" class="font-medium text-brand-700 hover:underline">{{ __('shopper-storefront::auth.login.forgot') }}</a>
            </div>

            <button type="submit" class="sf-button w-full">{{ __('shopper-storefront::auth.login.submit') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            {{ __('shopper-storefront::auth.login.no_account') }}
            <a href="{{ route('shopper.storefront.register') }}" class="font-semibold text-brand-700 hover:underline">{{ __('shopper-storefront::auth.register.title') }}</a>
        </p>
    </x-shopper-storefront::auth-card>
@endsection
