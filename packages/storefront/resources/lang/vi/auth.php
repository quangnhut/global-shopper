<?php

declare(strict_types=1);

return [
    'failed' => 'Email hoặc mật khẩu không đúng.',
    'throttle' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau :seconds giây.',
    'registered' => 'Tạo tài khoản thành công. Chào mừng bạn!',
    'reset_link_sent' => 'Nếu email này đã đăng ký, bạn sẽ nhận được liên kết đặt lại mật khẩu trong vài phút.',
    'reset_failed' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
    'password_reset' => 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.',
    'fields' => [
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'new_password' => 'Mật khẩu mới',
        'password_confirmation' => 'Nhập lại mật khẩu',
        'first_name' => 'Tên',
        'last_name' => 'Họ',
        'remember' => 'Ghi nhớ đăng nhập',
    ],
    'login' => [
        'title' => 'Đăng nhập',
        'subtitle' => 'Đăng nhập để mua sắm nhanh hơn.',
        'submit' => 'Đăng nhập',
        'forgot' => 'Quên mật khẩu?',
        'no_account' => 'Chưa có tài khoản?',
    ],
    'register' => [
        'title' => 'Đăng ký',
        'subtitle' => 'Tạo tài khoản miễn phí trong một phút.',
        'submit' => 'Tạo tài khoản',
        'has_account' => 'Đã có tài khoản?',
    ],
    'forgot' => [
        'title' => 'Quên mật khẩu',
        'subtitle' => 'Nhập email của bạn, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.',
        'submit' => 'Gửi liên kết',
        'back' => 'Quay lại đăng nhập',
    ],
    'reset' => [
        'title' => 'Đặt lại mật khẩu',
        'submit' => 'Đặt lại mật khẩu',
    ],
    'reset_mail' => [
        'subject' => 'Đặt lại mật khẩu tại :store',
        'intro' => 'Bạn nhận được email này vì có yêu cầu đặt lại mật khẩu cho tài khoản của bạn.',
        'action' => 'Đặt lại mật khẩu',
        'expire' => 'Liên kết sẽ hết hạn sau :count phút.',
        'outro' => 'Nếu bạn không yêu cầu, hãy bỏ qua email này.',
    ],
];
