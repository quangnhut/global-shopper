@extends('shopper-storefront::layout', ['seoContext' => []])

@section('content')
    <x-shopper-storefront::auth-card :title="__('shopper-storefront::auth.reset.title')">
        <form method="POST" action="{{ route('shopper.storefront.password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />
            <x-shopper-storefront::field name="email" type="email" :value="$email" :label="__('shopper-storefront::auth.fields.email')" autocomplete="email" />
            <x-shopper-storefront::field name="password" type="password" :label="__('shopper-storefront::auth.fields.new_password')" autocomplete="new-password" autofocus />
            <x-shopper-storefront::field name="password_confirmation" type="password" :label="__('shopper-storefront::auth.fields.password_confirmation')" autocomplete="new-password" />
            <button type="submit" class="sf-button w-full">{{ __('shopper-storefront::auth.reset.submit') }}</button>
        </form>
    </x-shopper-storefront::auth-card>
@endsection
