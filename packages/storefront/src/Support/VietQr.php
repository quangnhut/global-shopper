<?php

declare(strict_types=1);

namespace Shopper\Storefront\Support;

/**
 * Transfer QR images from img.vietqr.io (no API key). Banking apps fill in the
 * account, the amount and the transfer reference when the code is scanned.
 */
final class VietQr
{
    /**
     * @param  array{bin: string, number: string, name: string}  $account
     */
    public static function imageUrl(array $account, int $amount, string $reference): string
    {
        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?%s',
            rawurlencode($account['bin']),
            rawurlencode($account['number']),
            http_build_query(['amount' => $amount, 'addInfo' => $reference, 'accountName' => $account['name']], '', '&', PHP_QUERY_RFC3986),
        );
    }
}
