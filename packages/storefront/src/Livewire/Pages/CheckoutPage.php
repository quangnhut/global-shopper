<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Shopper\Core\Models\Contracts\Order as OrderContract;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Storefront\Actions\PlaceOrderAction;
use Shopper\Storefront\Cart\StorefrontCart;
use Shopper\Storefront\Checkout\CheckoutData;
use Shopper\Storefront\Checkout\CheckoutException;
use Shopper\Storefront\Checkout\CheckoutPaymentMethods;
use Shopper\Storefront\Checkout\OnePayCheckout;
use Shopper\Storefront\Checkout\OrderAccess;
use Shopper\Storefront\Http\Controllers\Auth\LoginController;
use Shopper\Storefront\Livewire\Concerns\ManagesCartLines;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\StorefrontUrls;

/**
 * The /checkout page body: contact, editable quantities, payment method, place order.
 * No address: orders are collected at the office.
 *
 * @property-read Collection<int, PaymentMethod> $paymentMethods
 */
class CheckoutPage extends Component
{
    use ManagesCartLines;

    private const int MAX_ATTEMPTS = 5;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $note = '';

    public string $paymentMethod = '';

    public bool $acceptTerms = false;

    public function mount(): void
    {
        $this->refreshCart();

        if ($this->lines === []) {
            $this->redirect(StorefrontUrls::to('cart'));

            return;
        }

        $user = Auth::guard(LoginController::guard())->user();

        if ($user !== null) {
            $this->name = (string) ($user->full_name ?? $user->name ?? '');
            $this->email = (string) ($user->email ?? '');
            $this->phone = (string) ($user->phone_number ?? '');
        }

        $this->paymentMethod = (string) $this->paymentMethods->first()?->slug;
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): Collection
    {
        return resolve(CheckoutPaymentMethods::class)->available();
    }

    public function placeOrder(PlaceOrderAction $placeOrder): void
    {
        $key = 'storefront-checkout:'.session()->getId().'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->addError('cart', __('shopper-storefront::checkout.errors.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key);

        $this->phone = CheckoutData::normalizePhone($this->phone);
        $this->name = mb_trim(preg_replace('/\s+/u', ' ', $this->name) ?? '');

        $rules = CheckoutData::rules($this->paymentMethods->pluck('slug')->all());
        $fields = ['name' => 'name', 'phone' => 'phone', 'email' => 'email', 'note' => 'note', 'payment_method' => 'paymentMethod', 'accept_terms' => 'acceptTerms'];
        $attributes = CheckoutData::attributes();

        $validated = $this->validate(
            collect($fields)->mapWithKeys(fn (string $property, string $field): array => [$property => $rules[$field]])->all(),
            messages: [...CheckoutData::messages(), 'phone.regex' => __('shopper-storefront::checkout.validation.phone')],
            attributes: collect($fields)->mapWithKeys(fn (string $property, string $field): array => [$property => $attributes[$field]])->all(),
        );

        $cart = resolve(StorefrontCart::class)->current();

        if ($cart === null) {
            // Already placed by an earlier click: go to the latest order of this session.
            $this->redirectToLatestOrder();

            return;
        }

        try {
            $order = $placeOrder->execute($cart, CheckoutData::fromValidated([
                ...$validated,
                'payment_method' => $validated['paymentMethod'],
            ], request()->ip()));
        } catch (CheckoutException $exception) {
            $this->notices = $exception->notices;
            $this->addError('cart', $exception->getMessage());
            $this->forgetCart();

            return;
        }

        RateLimiter::clear($key);
        $this->dispatch('cart-updated');

        if (OnePayCheckout::needsRedirect($order)) {
            $url = OnePayCheckout::start($order);

            if ($url !== null) {
                $this->redirect($url);

                return;
            }
        }

        $this->redirect(route('shopper.storefront.order.thanks', $order->number));
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.checkout-page', [
            'settings' => resolve(StorefrontSettings::class),
            'policies' => Legal::query()->enabled()->orderBy('title')->get(['title', 'slug']),
        ]);
    }

    private function redirectToLatestOrder(): void
    {
        $ids = (array) session(OrderAccess::SESSION_KEY, []);
        $order = $ids !== [] ? resolve(OrderContract::class)::query()->find(end($ids)) : null;

        $this->redirect($order !== null
            ? route('shopper.storefront.order.thanks', $order->number)
            : StorefrontUrls::to('cart'));
    }
}
