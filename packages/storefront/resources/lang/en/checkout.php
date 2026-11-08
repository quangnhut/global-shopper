<?php

declare(strict_types=1);

return [
    'title' => 'Checkout',
    'contact' => 'Contact details',
    'optional' => 'optional',
    'guest' => 'You can order without an account. Already have one?',
    'sign_in' => 'Sign in',
    'note_placeholder' => 'For example: when you would like to collect your order',
    'terms' => 'I agree to the',
    'place_order' => 'Place order',
    'placing' => 'Placing your order…',
    'back_to_cart' => 'Back to cart',
    'fields' => [
        'name' => 'Full name',
        'phone' => 'Phone number',
        'email' => 'Email',
        'note' => 'Note',
        'payment_method' => 'Payment method',
        'terms' => 'terms',
    ],
    'pickup' => [
        'title' => 'Collect at our office',
        'intro' => 'We do not deliver. You collect your order at our office when it is ready.',
        'hours' => 'Opening hours: :hours',
    ],
    'office' => [
        'instructions' => 'Come to our office, give order number :number and pay in cash or by card when you collect it.',
    ],
    'bank' => [
        'bank' => 'Bank',
        'account_number' => 'Account number',
        'account_name' => 'Account holder',
        'amount' => 'Amount',
        'content' => 'Transfer reference',
        'confirm' => 'We confirm your order when the money arrives. Please use the exact transfer reference.',
        'missing' => 'We will call you with the bank details. Transfer reference: :number.',
    ],
    'onepay' => [
        'paid' => 'This order was paid with OnePay.',
        'pending' => 'This order is not paid yet.',
        'pay_again' => 'Pay with OnePay',
    ],
    'thanks' => [
        'title' => 'Thank you for your order!',
        'number' => 'Order number:',
        'contact_soon' => 'We will call :phone when your order is ready.',
        'items' => 'Items',
        'qr_alt' => 'VietQR transfer code for order :number',
    ],
    'errors' => [
        'payment_method' => 'This payment method is no longer available.',
        'no_payment_method' => 'No payment method is available right now. Please call our hotline to order.',
        'cart_changed' => 'Your cart changed. Please check it before placing the order.',
        'empty' => 'Your cart is empty.',
        'stock' => 'An item just sold out. Please check your cart.',
        'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
    ],
    'validation' => [
        'required' => 'Please enter your :attribute.',
        'min' => 'The :attribute must have at least :min characters.',
        'max' => 'The :attribute may not have more than :max characters.',
        'email' => 'This email address is not valid.',
        'phone' => 'This phone number is not valid (e.g. 0901 234 567).',
        'in' => 'Please choose a :attribute.',
        'accepted' => 'Please accept the terms to place your order.',
    ],
];
