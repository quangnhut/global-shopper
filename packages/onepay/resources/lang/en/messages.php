<?php

declare(strict_types=1);

return [
    'not_configured' => 'OnePay is not configured.',
    'query_not_configured' => 'The OnePay QueryDR account is not configured.',
    'currency' => 'OnePay domestic only accepts VND.',
    'unreachable' => 'OnePay could not be reached. Please try again later.',
    'not_found' => 'OnePay has no record of this payment.',
    'bad_signature' => 'The OnePay response signature is invalid.',
    'not_payable' => 'This order does not need to be paid.',
    'invalid_return' => 'The payment result could not be verified. If you were charged, please contact the shop.',
    'pending_confirmation' => 'Waiting for OnePay to confirm the payment. Please reload this page in a few minutes.',
    'paid' => 'Payment received. Thank you!',
    'amount_problem' => 'The amount paid does not match the order. The shop will contact you.',
    'amount_mismatch' => 'OnePay reported :paid, the order total is :expected.',
];
