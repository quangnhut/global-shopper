<?php

declare(strict_types=1);

namespace Shopper\Storefront\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Shopper\Cart\CartSessionManager;
use Shopper\Cart\Models\Cart;
use Shopper\Cart\Models\CartLine;
use Shopper\Core\Models\Contracts\Stockable;
use Shopper\Core\Models\Product;
use Shopper\Core\Models\ProductVariant;
use Shopper\Storefront\Http\Controllers\Auth\LoginController;

/**
 * The visitor's cart, as the storefront sees it: the session cart on the Blade pages,
 * or the cart named by the `X-Cart-Token` header on the JSON API (one instance per request).
 */
final class StorefrontCart
{
    /** Highest quantity of one line, whatever the stock. */
    public const int MAX_QUANTITY = 99;

    private bool $usesToken = false;

    private ?Cart $tokenCart = null;

    public function __construct(private readonly CartSessionManager $sessions) {}

    /**
     * The product a purchasable belongs to (itself, or the variant's product).
     */
    public static function productOf(?Model $purchasable): ?Product
    {
        return match (true) {
            $purchasable instanceof ProductVariant => $purchasable->product instanceof Product ? $purchasable->product : null,
            $purchasable instanceof Product => $purchasable,
            default => null,
        };
    }

    public static function isSellable(?Model $purchasable): bool
    {
        $product = self::productOf($purchasable);

        return $product !== null && $product->isPublished();
    }

    /**
     * How many of this purchasable can be sold, capped at `MAX_QUANTITY`.
     */
    public static function available(Model $purchasable): int
    {
        if (! $purchasable instanceof Stockable || ! $purchasable->tracksInventory() || $purchasable->getAttribute('allow_backorder')) {
            return self::MAX_QUANTITY;
        }

        return max(0, min(self::MAX_QUANTITY, $purchasable->getStock()));
    }

    public static function displayName(Model $purchasable): string
    {
        if ($purchasable instanceof ProductVariant) {
            return mb_trim(($purchasable->product->name ?? '').' - '.$purchasable->name, ' -');
        }

        return (string) $purchasable->getAttribute('name');
    }

    /**
     * Headless mode: the cart is found by its token, never through the session.
     */
    public function useToken(?string $token): void
    {
        $this->usesToken = true;
        $this->tokenCart = filled($token) && mb_strlen((string) $token) <= 64
            ? Cart::query()->where('metadata->token', $token)->whereNull('completed_at')->first()
            : null;
    }

    public function usesToken(): bool
    {
        return $this->usesToken;
    }

    public function token(): ?string
    {
        $token = $this->current()?->metadata['token'] ?? null;

        return is_string($token) ? $token : null;
    }

    /**
     * The open cart of this visitor, never created here.
     */
    public function current(): ?Cart
    {
        if ($this->usesToken) {
            return $this->tokenCart !== null && ! $this->tokenCart->refresh()->isCompleted() ? $this->tokenCart : null;
        }

        $id = session((string) config('shopper.cart.session.key', 'shopper_cart'));

        if (! $id) {
            return null;
        }

        $cart = Cart::query()->find($id);

        return $cart instanceof Cart && ! $cart->isCompleted() ? $cart : null;
    }

    public function currentOrCreate(): Cart
    {
        $current = $this->current();

        if ($current !== null) {
            return $current;
        }

        if ($this->usesToken) {
            return $this->tokenCart = Cart::query()->create([
                'currency_code' => shopper_currency(),
                'metadata' => ['token' => Str::random(48)],
            ]);
        }

        return $this->sessions->create(array_filter([
            'customer_id' => Auth::guard(LoginController::guard())->id(),
        ]));
    }

    /**
     * Forgets the cart once it became an order.
     */
    public function forget(): void
    {
        if ($this->usesToken) {
            $this->tokenCart = null;

            return;
        }

        $this->sessions->forget();
    }

    public function line(Cart $cart, int $lineId): CartLine
    {
        $line = $cart->lines()->with('purchasable')->find($lineId);

        if (! $line instanceof CartLine) {
            throw CartException::lineNotFound();
        }

        return $line;
    }
}
