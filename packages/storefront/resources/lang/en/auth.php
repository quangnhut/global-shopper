<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
    'registered' => 'Your account is ready. Welcome!',
    'reset_link_sent' => 'If this email has an account, a reset link is on its way.',
    'reset_failed' => 'This password reset link is invalid or has expired.',
    'password_reset' => 'Your password has been reset. You can sign in with it now.',
    'fields' => [
        'email' => 'Email',
        'password' => 'Password',
        'new_password' => 'New password',
        'password_confirmation' => 'Confirm password',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'remember' => 'Remember me',
    ],
    'login' => [
        'title' => 'Sign in',
        'subtitle' => 'Sign in for a faster checkout.',
        'submit' => 'Sign in',
        'forgot' => 'Forgot your password?',
        'no_account' => 'No account yet?',
    ],
    'register' => [
        'title' => 'Sign up',
        'subtitle' => 'Create a free account in a minute.',
        'submit' => 'Create account',
        'has_account' => 'Already have an account?',
    ],
    'forgot' => [
        'title' => 'Forgot password',
        'subtitle' => 'Enter your email and we will send you a reset link.',
        'submit' => 'Send reset link',
        'back' => 'Back to sign in',
    ],
    'reset' => [
        'title' => 'Reset password',
        'submit' => 'Reset password',
    ],
    'reset_mail' => [
        'subject' => 'Reset your password at :store',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expire' => 'This link will expire in :count minutes.',
        'outro' => 'If you did not request a password reset, no further action is required.',
    ],
];
