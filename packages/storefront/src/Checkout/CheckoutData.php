<?php

declare(strict_types=1);

namespace Shopper\Storefront\Checkout;

use Illuminate\Validation\Rule;

/**
 * What the customer typed on the checkout page, already validated.
 */
final readonly class CheckoutData
{
    /** 0xxxxxxxxx, 10 or 11 digits, once spaces and dots are removed. */
    public const string PHONE_PATTERN = '/^0\d{9,10}$/';

    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $note,
        public string $paymentMethod,
        public ?string $ip = null,
    ) {}

    /**
     * Validation of the checkout form (field names as on the JSON API).
     *
     * @param  array<int, string>  $paymentSlugs
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $paymentSlugs): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:'.(int) config('shopper.storefront.checkout.note_max_length', 1000)],
            'payment_method' => ['required', 'string', Rule::in($paymentSlugs)],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => __('shopper-storefront::checkout.fields.name'),
            'phone' => __('shopper-storefront::checkout.fields.phone'),
            'email' => __('shopper-storefront::checkout.fields.email'),
            'note' => __('shopper-storefront::checkout.fields.note'),
            'payment_method' => __('shopper-storefront::checkout.fields.payment_method'),
            'accept_terms' => __('shopper-storefront::checkout.fields.terms'),
        ];
    }

    /**
     * Messages in the storefront language (the application may have no `validation.php` for it).
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'required' => __('shopper-storefront::checkout.validation.required'),
            'min' => __('shopper-storefront::checkout.validation.min'),
            'max' => __('shopper-storefront::checkout.validation.max'),
            'email' => __('shopper-storefront::checkout.validation.email'),
            'phone.regex' => __('shopper-storefront::checkout.validation.phone'),
            'in' => __('shopper-storefront::checkout.validation.in'),
            'accepted' => __('shopper-storefront::checkout.validation.accepted'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  output of `rules()`
     */
    public static function fromValidated(array $validated, ?string $ip): self
    {
        return new self(
            name: mb_trim(preg_replace('/\s+/u', ' ', (string) $validated['name']) ?? ''),
            phone: self::normalizePhone((string) $validated['phone']),
            email: filled($validated['email'] ?? null) ? mb_strtolower(mb_trim((string) $validated['email'])) : null,
            note: filled($validated['note'] ?? null) ? mb_trim(strip_tags((string) $validated['note'])) : null,
            paymentMethod: (string) $validated['payment_method'],
            ip: $ip,
        );
    }

    /**
     * 0901 234.567 / +84 901234567 → 0901234567.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[\s.\-()]/', '', $phone) ?? '';

        return str_starts_with($digits, '+84') ? '0'.mb_substr($digits, 3) : $digits;
    }
}
