<?php

declare(strict_types=1);

namespace Shopper\OnePay;

enum ConfirmationOutcome: string
{
    case Paid = 'paid';
    case AlreadyPaid = 'already_paid';
    case Failed = 'failed';
    case AmountMismatch = 'amount_mismatch';
    case UnknownReference = 'unknown_reference';
    case Invalid = 'invalid';

    /**
     * What OnePay's IPN expects back: 1 = we took it into account, 0 = send it again.
     */
    public function acknowledged(): bool
    {
        return $this !== self::Invalid;
    }
}
