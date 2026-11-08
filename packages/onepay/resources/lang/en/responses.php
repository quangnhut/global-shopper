<?php

declare(strict_types=1);

// vpc_TxnResponseCode of the OnePay domestic gateway.
return [
    '0' => 'Payment successful.',
    '1' => 'The bank declined the payment.',
    '3' => 'Unknown merchant.',
    '4' => 'Wrong access code.',
    '5' => 'Invalid amount.',
    '6' => 'Unknown currency.',
    '7' => 'Unknown error.',
    '8' => 'Wrong card number.',
    '9' => 'Wrong card holder name.',
    '10' => 'The card has expired or is locked.',
    '11' => 'The card is not registered for online payments.',
    '12' => 'Wrong issue or expiry date.',
    '13' => 'Payment limit exceeded.',
    '21' => 'Insufficient funds.',
    '22' => 'Wrong account details.',
    '23' => 'The account is locked.',
    '24' => 'Wrong card details.',
    '25' => 'Wrong OTP.',
    '99' => 'You cancelled the payment.',
    '253' => 'The payment timed out.',
    'unknown' => 'The payment failed (code :code).',
];
