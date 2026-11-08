<?php

declare(strict_types=1);

namespace Shopper\OnePay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Shopper\OnePay\Actions\ConfirmOnePayPaymentAction;
use Shopper\OnePay\OnePayDriver;
use Shopper\Payment\Facades\Payment;

/**
 * Server-to-server confirmation from OnePay. Answers in OnePay's format:
 * `responsecode=1` = received (do not resend), `responsecode=0` = resend.
 */
final class IpnController
{
    public function __invoke(Request $request, ConfirmOnePayPaymentAction $confirm): Response
    {
        /** @var OnePayDriver $driver */
        $driver = Payment::driver(OnePayDriver::CODE);
        $result = $driver->handleWebhook($request->all());
        $outcome = $confirm->execute($result);

        if (! $outcome->acknowledged()) {
            Log::warning('OnePay IPN rejected: bad signature or missing reference.', ['reference' => $request->input('vpc_MerchTxnRef')]);
        }

        return response(
            $outcome->acknowledged() ? 'responsecode=1&desc=confirm-success' : 'responsecode=0&desc=confirm-fail',
            200,
            ['Content-Type' => 'text/plain'],
        );
    }
}
