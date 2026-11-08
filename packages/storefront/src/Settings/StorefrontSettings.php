<?php

declare(strict_types=1);

namespace Shopper\Storefront\Settings;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Shopper\Core\Models\Setting;

/**
 * Header and footer options of the storefront, one `Setting` row per key (`storefront_hotline`, …),
 * merged with defaults and cached until the next save. Company details the store already has
 * (name, address, email, phone, logo) are read from Settings → General and used as fallbacks.
 */
final class StorefrontSettings
{
    public const string CACHE_KEY = 'shopper-storefront:settings';

    public const string PREFIX = 'storefront_';

    /**
     * @var array<string, mixed>
     */
    public const array DEFAULTS = [
        // Header
        'hotline' => null,
        'header_logo' => null,
        'announcement' => null,
        'menu_category_ids' => [],
        'translate_enabled' => true,
        // Company
        'working_hours' => null,
        'business_registration' => null,
        'registration_issued' => null,
        // Footer
        'footer_columns' => [],
        'dmca_url' => null,
        'dmca_badge' => null,
        'bct_url' => null,
        'bct_badge' => null,
        'carrier_ids' => [],
        'payment_method_ids' => [],
        'extra_partners' => [],
        // Checkout: bank account of the "online pay" (VietQR transfer) method, pickup instructions
        'bank_bin' => null,
        'bank_name' => null,
        'bank_account_number' => null,
        'bank_account_name' => null,
        'pickup_note' => null,
    ];

    /**
     * Store settings (Settings → General) the storefront reads.
     */
    public const array STORE_KEYS = [
        'name', 'legal_name', 'email', 'phone_number', 'logo', 'street_address', 'city', 'postal_code',
        'country_id', 'facebook_link', 'instagram_link', 'twitter_link',
    ];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) && $values[$key] !== null ? $values[$key] : $default;
    }

    /**
     * Storefront keys (without prefix) merged with defaults, plus the store keys under `store.*`.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            $rows = Setting::query()
                ->whereIn('key', [
                    ...array_map(fn (string $key): string => self::PREFIX.$key, array_keys(self::DEFAULTS)),
                    ...self::STORE_KEYS,
                ])
                ->pluck('value', 'key');

            $values = self::DEFAULTS;

            foreach (array_keys(self::DEFAULTS) as $key) {
                if ($rows->has(self::PREFIX.$key)) {
                    $values[$key] = $rows->get(self::PREFIX.$key);
                }
            }

            foreach (self::STORE_KEYS as $key) {
                $values["store.{$key}"] = $rows->get($key);
            }

            return $values;
        });
    }

    /**
     * @param  array<string, mixed>  $values  keys without the `storefront_` prefix
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                throw new InvalidArgumentException("Unknown storefront setting [{$key}].");
            }

            Setting::query()->updateOrCreate(
                ['key' => self::PREFIX.$key],
                ['value' => $value, 'display_name' => $key, 'locked' => true],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    /**
     * The account customers transfer to, when every part of it is filled in.
     *
     * @return array{bin: string, bank: string, number: string, name: string}|null
     */
    public function bankAccount(): ?array
    {
        $account = [
            'bin' => preg_replace('/\D/', '', (string) $this->get('bank_bin')) ?? '',
            'bank' => mb_trim((string) $this->get('bank_name')),
            'number' => preg_replace('/\s/', '', (string) $this->get('bank_account_number')) ?? '',
            'name' => mb_trim((string) $this->get('bank_account_name')),
        ];

        return in_array('', $account, true) ? null : $account;
    }

    public function companyName(): ?string
    {
        return $this->filled('store.legal_name') ?? $this->filled('store.name');
    }

    public function storeName(): ?string
    {
        return $this->filled('store.name') ?? $this->filled('store.legal_name');
    }

    /**
     * The hotline, or the store phone number when no hotline is set.
     */
    public function hotline(): ?string
    {
        return $this->filled('hotline') ?? $this->filled('store.phone_number');
    }

    /**
     * Upload id or path of the header logo, falling back to the store logo.
     */
    public function logo(): mixed
    {
        return $this->get('header_logo') ?? $this->get('store.logo');
    }

    public function address(): ?string
    {
        $parts = array_filter([
            $this->filled('store.street_address'),
            $this->filled('store.city'),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Details Vietnamese law requires in an online shop's footer (Decree 52/2013, amended by 85/2021)
     * that are still empty.
     *
     * @return array<int, string>
     */
    public function missingLegalDetails(): array
    {
        return array_keys(array_filter([
            'company_name' => $this->companyName() === null,
            'address' => $this->address() === null,
            'business_registration' => $this->filled('business_registration') === null,
            'phone' => $this->hotline() === null,
            'email' => $this->filled('store.email') === null,
            'bct_url' => $this->filled('bct_url') === null,
        ]));
    }

    private function filled(string $key): ?string
    {
        $value = $this->get($key);

        return is_scalar($value) && mb_trim((string) $value) !== '' ? mb_trim((string) $value) : null;
    }
}
