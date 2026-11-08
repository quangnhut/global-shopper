<?php

declare(strict_types=1);

namespace Shopper\OnePay;

/**
 * OnePay's `vpc_SecureHash`: HMAC-SHA256 over the sorted, non-empty `vpc_*` and
 * `user_*` parameters (`key=value` joined with `&`), keyed with the hex-decoded
 * merchant secret, in upper-case hex.
 */
final readonly class OnePaySigner
{
    public function __construct(private string $hashSecret) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function stringToSign(array $parameters): string
    {
        $signed = array_filter(
            $parameters,
            fn (mixed $value, string|int $key): bool => (str_starts_with((string) $key, 'vpc_') || str_starts_with((string) $key, 'user_'))
                && ! in_array($key, ['vpc_SecureHash', 'vpc_SecureHashType'], true)
                && is_scalar($value)
                && (string) $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        ksort($signed, SORT_STRING);

        return implode('&', array_map(fn (string $key, mixed $value): string => $key.'='.$value, array_keys($signed), $signed));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function sign(array $parameters): string
    {
        $secret = @hex2bin($this->hashSecret);

        return mb_strtoupper(hash_hmac('sha256', self::stringToSign($parameters), $secret !== false ? $secret : $this->hashSecret));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function verify(array $parameters): bool
    {
        $given = $parameters['vpc_SecureHash'] ?? '';

        return is_scalar($given) && (string) $given !== '' && hash_equals($this->sign($parameters), mb_strtoupper((string) $given));
    }
}
