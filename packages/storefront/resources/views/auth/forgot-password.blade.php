@extends('shopper-storefront::layout', ['seoContext' => []])

@section('content')
    <x-shopper-storefront::auth-card :title="__('shopper-storefront::auth.forgot.title')" :subtitle="__('shopper-storefront::auth.forgot.subtitle')">
        <form method="POST" action="{{ route('shopper.storefront.password.email') }}" class="space-y-4">
            @csrf
            <x-shopper-storefront::field name="email" type="email" :label="__('shopper-storefront::auth.fields.email')" autocomplete="email" autofocus />
            <button type="submit" class="sf-button w-full">{{ __('shopper-storefront::auth.forgot.submit') }}</button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('shopper.storefront.login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('shopper-storefront::auth.forgot.back') }}</a>
        </p>
    </x-shopper-storefront::auth-card>
@endsection
