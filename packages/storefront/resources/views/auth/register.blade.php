@extends('shopper-storefront::layout', ['seoContext' => []])

@section('content')
    <x-shopper-storefront::auth-card :title="__('shopper-storefront::auth.register.title')" :subtitle="__('shopper-storefront::auth.register.subtitle')">
        <form method="POST" action="{{ route('shopper.storefront.register.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-shopper-storefront::field name="last_name" :label="__('shopper-storefront::auth.fields.last_name')" autocomplete="family-name" autofocus />
                <x-shopper-storefront::field name="first_name" :label="__('shopper-storefront::auth.fields.first_name')" autocomplete="given-name" />
            </div>
            <x-shopper-storefront::field name="email" type="email" :label="__('shopper-storefront::auth.fields.email')" autocomplete="email" />
            <x-shopper-storefront::field name="password" type="password" :label="__('shopper-storefront::auth.fields.password')" autocomplete="new-password" />
            <x-shopper-storefront::field name="password_confirmation" type="password" :label="__('shopper-storefront::auth.fields.password_confirmation')" autocomplete="new-password" />

            <button type="submit" class="sf-button w-full">{{ __('shopper-storefront::auth.register.submit') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            {{ __('shopper-storefront::auth.register.has_account') }}
            <a href="{{ route('shopper.storefront.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('shopper-storefront::auth.login.title') }}</a>
        </p>
    </x-shopper-storefront::auth-card>
@endsection
