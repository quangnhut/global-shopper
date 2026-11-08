@extends('shopper-storefront::layout', ['seoContext' => ['title' => __('shopper-storefront::checkout.title'), 'noindex' => true]])

@section('content')
    <livewire:shopper-storefront.checkout-page />
@endsection
