@extends('shopper-storefront::layout', ['seoContext' => ['title' => __('shopper-storefront::cart.title'), 'noindex' => true]])

@section('content')
    <livewire:shopper-storefront.cart-page />
@endsection
