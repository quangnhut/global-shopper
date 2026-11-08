@php
    /** @var \Shopper\Storefront\Settings\StorefrontSettings $settings */
    $address = $settings->address();
    $hours = $settings->get('working_hours');
@endphp

<div class="sf-container py-6">
    <h1 class="text-xl font-bold text-gray-900">{{ __('shopper-storefront::checkout.title') }}</h1>

    @include('shopper-storefront::partials.cart-notices')

    @if ($this->lines === [])
        <div class="py-20 text-center">
            <p class="text-gray-500">{{ __('shopper-storefront::cart.empty') }}</p>
            <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::home() }}" class="sf-button mt-6">{{ __('shopper-storefront::cart.continue') }}</a>
        </div>
    @else
        <form wire:submit="placeOrder" class="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem] lg:items-start" novalidate>
            <div class="min-w-0 space-y-6">
                <section class="rounded-xl bg-white p-4 ring-1 ring-gray-100" aria-labelledby="sf-contact-title">
                    <h2 id="sf-contact-title" class="font-semibold text-gray-900">{{ __('shopper-storefront::checkout.contact') }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="sf-checkout-name" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::checkout.fields.name') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="sf-checkout-name" type="text" wire:model="name" autocomplete="name" required maxlength="120" class="sf-input mt-1" @error('name') aria-invalid="true" aria-describedby="sf-checkout-name-error" @enderror />
                            @error('name') <p id="sf-checkout-name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="sf-checkout-phone" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::checkout.fields.phone') }} <span class="text-red-600" aria-hidden="true">*</span></label>
                            <input id="sf-checkout-phone" type="tel" inputmode="tel" wire:model="phone" autocomplete="tel" required maxlength="20" placeholder="0901 234 567" class="sf-input mt-1" @error('phone') aria-invalid="true" aria-describedby="sf-checkout-phone-error" @enderror />
                            @error('phone') <p id="sf-checkout-phone-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="sf-checkout-email" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::checkout.fields.email') }} <span class="text-gray-500">({{ __('shopper-storefront::checkout.optional') }})</span></label>
                            <input id="sf-checkout-email" type="email" wire:model="email" autocomplete="email" maxlength="255" class="sf-input mt-1" @error('email') aria-invalid="true" aria-describedby="sf-checkout-email-error" @enderror />
                            @error('email') <p id="sf-checkout-email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="sf-checkout-note" class="block text-sm font-medium text-gray-700">{{ __('shopper-storefront::checkout.fields.note') }} <span class="text-gray-500">({{ __('shopper-storefront::checkout.optional') }})</span></label>
                            <textarea id="sf-checkout-note" wire:model="note" rows="3" maxlength="{{ (int) config('shopper.storefront.checkout.note_max_length', 1000) }}" placeholder="{{ __('shopper-storefront::checkout.note_placeholder') }}" class="sf-input mt-1" @error('note') aria-invalid="true" @enderror></textarea>
                            @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @guest(\Shopper\Storefront\Http\Controllers\Auth\LoginController::guard())
                        <p class="mt-3 text-sm text-gray-600">
                            {{ __('shopper-storefront::checkout.guest') }}
                            <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::to('login') }}" class="font-medium text-brand-700 hover:underline">{{ __('shopper-storefront::checkout.sign_in') }}</a>
                        </p>
                    @endguest
                </section>

                <section class="rounded-xl bg-white p-4 ring-1 ring-gray-100" aria-labelledby="sf-pickup-title">
                    <h2 id="sf-pickup-title" class="flex items-center gap-2 font-semibold text-gray-900"><x-shopper-storefront::icon name="store" class="size-5 text-brand-600" /> {{ __('shopper-storefront::checkout.pickup.title') }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ __('shopper-storefront::checkout.pickup.intro') }}</p>
                    @if ($address)
                        <p class="mt-2 text-sm font-medium text-gray-800">{{ $address }}</p>
                    @endif
                    @if ($hours)
                        <p class="text-sm text-gray-600">{{ __('shopper-storefront::checkout.pickup.hours', ['hours' => $hours]) }}</p>
                    @endif
                    @if ($settings->get('pickup_note'))
                        <p class="mt-2 text-sm text-gray-600">{{ $settings->get('pickup_note') }}</p>
                    @endif
                </section>

                <fieldset class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-gray-100">
                    <legend class="float-left mb-3 w-full font-semibold text-gray-900">{{ __('shopper-storefront::checkout.fields.payment_method') }}</legend>
                    @if ($this->paymentMethods->isEmpty())
                        <p class="clear-both text-sm text-red-600">{{ __('shopper-storefront::checkout.errors.no_payment_method') }}</p>
                    @endif
                    <div class="clear-both space-y-2">
                        @foreach ($this->paymentMethods as $method)
                            <label wire:key="pm-{{ $method->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 has-[:checked]:bg-brand-50 has-[:checked]:ring-brand-500">
                                <input type="radio" wire:model="paymentMethod" value="{{ $method->slug }}" name="payment_method" class="mt-1 accent-brand-600" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $method->title }}</span>
                                    @if ($method->description)
                                        <span class="block text-sm text-gray-600">{{ $method->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('paymentMethod') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>
            </div>

            <aside class="min-w-0 space-y-4 rounded-xl bg-white p-4 ring-1 ring-gray-100 lg:sticky lg:top-4">
                <h2 class="font-semibold text-gray-900">{{ __('shopper-storefront::cart.summary') }}</h2>
                <div wire:loading.class="opacity-60" wire:target="increment,decrement,setQuantity,remove">
                    @include('shopper-storefront::partials.cart-lines', ['lines' => $this->lines])
                </div>
                @include('shopper-storefront::partials.cart-summary', ['summary' => $this->summary])

                <div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="acceptTerms" class="mt-0.5 accent-brand-600" @error('acceptTerms') aria-invalid="true" @enderror />
                        <span>
                            {{ __('shopper-storefront::checkout.terms') }}
                            @foreach ($policies as $policy)
                                <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::to('legal', ['slug' => $policy->slug]) }}" target="_blank" class="text-brand-700 underline">{{ $policy->title }}</a>@if (! $loop->last), @endif
                            @endforeach
                        </span>
                    </label>
                    @error('acceptTerms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="sf-button w-full py-3 text-base" wire:loading.attr="disabled" wire:target="placeOrder" @disabled($this->paymentMethods->isEmpty())>
                    <span wire:loading.remove wire:target="placeOrder">{{ __('shopper-storefront::checkout.place_order') }}</span>
                    <span wire:loading wire:target="placeOrder">{{ __('shopper-storefront::checkout.placing') }}</span>
                </button>
                <a href="{{ \Shopper\Storefront\Support\StorefrontUrls::to('cart') }}" class="block text-center text-sm text-brand-700 hover:underline">{{ __('shopper-storefront::checkout.back_to_cart') }}</a>
            </aside>
        </form>
    @endif
</div>
