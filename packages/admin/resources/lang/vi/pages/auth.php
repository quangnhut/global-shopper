<?php

declare(strict_types=1);

return [

    'login' => [
        'title' => 'Đăng nhập bằng email',
        'subtitle' => 'Đăng nhập vào trang quản trị Shopper',
        'or' => 'Hoặc',
        'return_landing' => 'Quay lại trang chủ',
        'forgot_password' => 'Quên mật khẩu?',
        'action' => 'Đăng nhập',
        'failed' => 'Thông tin đăng nhập không khớp với dữ liệu của chúng tôi.',
        'throttled' => 'Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau :seconds giây.',
        'return_login' => 'Quay lại đăng nhập',
    ],

    'reset' => [
        'title' => 'Đặt lại mật khẩu',
        'message' => 'Nhập email và mật khẩu mới mà bạn muốn dùng để truy cập tài khoản.',
        'action' => 'Cập nhật mật khẩu',
    ],

    'email' => [
        'title' => 'Đặt lại mật khẩu của bạn',
        'message' => 'Nhập email của bạn bên dưới, chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu.',
        'action' => 'Gửi email đặt lại mật khẩu',
        'return_to_login' => 'Quay lại trang đăng nhập',
        'mail' => [
            'subject' => 'Đặt lại mật khẩu',
            'content' => 'Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.',
            'action' => 'Đặt lại mật khẩu',
            'message' => 'Nếu bạn không yêu cầu đặt lại mật khẩu, bạn không cần làm gì thêm.',
        ],
    ],

    'two_factor' => [
        'title' => 'Đăng nhập với xác thực hai yếu tố',
        'subtitle' => 'Xác thực tài khoản của bạn',
        'authentication_code' => 'Vui lòng xác nhận quyền truy cập tài khoản bằng cách nhập mã xác thực từ ứng dụng xác thực của bạn.',
        'recovery_code' => 'Vui lòng xác nhận quyền truy cập tài khoản bằng cách nhập một trong các mã khôi phục khẩn cấp.',
        'remember' => 'Không nhớ mã này?',
        'use_recovery_code' => 'Dùng mã khôi phục',
        'use_authentication_code' => 'Dùng mã xác thực',
        'action' => 'Đăng nhập',
        'recovery_not_enabled' => 'Tài khoản này chưa bật mã khôi phục.',
        'invalid_recovery_code' => 'Mã khôi phục hai yếu tố không hợp lệ.',
        'invalid_code' => 'Mã xác thực hai yếu tố không hợp lệ.',
    ],

    'account' => [
        'meta_title' => 'Hồ sơ tài khoản',
        'title' => 'Hồ sơ của tôi',

        'device_title' => 'Thiết bị',
        'device_description' => 'Bạn đang đăng nhập trên các thiết bị này. Nếu không nhận ra thiết bị nào, hãy đăng xuất để giữ an toàn cho tài khoản.',
        'empty_device' => 'Nếu cần, bạn có thể đăng xuất khỏi tất cả các phiên trình duyệt khác trên mọi thiết bị.',
        'current_device' => 'Thiết bị này',
        'device_last_activity' => 'Hoạt động lần cuối',
        'device_location' => 'Không thể xác định vị trí này.',
        'device_enabled_feature' => 'Cần dùng session driver database để bật tính năng này.',

        'password_title' => 'Cập nhật mật khẩu',
        'password_description' => 'Hãy đảm bảo tài khoản của bạn dùng mật khẩu dài và ngẫu nhiên để giữ an toàn.',
        'password_helper_validation' => 'Mật khẩu phải dài hơn 8 ký tự và chứa ít nhất 1 chữ hoa, 1 chữ thường và 1 chữ số.',

        'two_factor_title' => 'Xác thực hai yếu tố',
        'two_factor_description' => 'Sau khi nhập mật khẩu, xác minh danh tính của bạn bằng phương thức xác thực thứ hai.',
        'two_factor_enabled' => 'Bạn đã bật xác thực hai yếu tố.',
        'two_factor_disabled' => 'Bạn chưa bật xác thực hai yếu tố.',
        'two_factor_install_message' => 'Để dùng xác thực hai yếu tố, bạn cần cài ứng dụng Google Authenticator trên điện thoại.',
        'two_factor_secure' => 'Với xác thực hai yếu tố, chỉ bạn mới có thể truy cập tài khoản — kể cả khi người khác có mật khẩu của bạn.',
        'two_factor_activation_message' => 'Khi bật xác thực hai yếu tố, bạn sẽ được yêu cầu nhập một mã ngẫu nhiên bảo mật khi đăng nhập. Bạn có thể lấy mã này từ ứng dụng Google Authenticator trên điện thoại.',
        'two_factor_is_enabled' => 'Xác thực hai yếu tố đã được bật. Hãy quét mã QR sau bằng ứng dụng xác thực trên điện thoại.',
        'two_factor_store_recovery_codes' => 'Hãy lưu các mã khôi phục này trong trình quản lý mật khẩu an toàn. Chúng có thể dùng để lấy lại quyền truy cập tài khoản nếu bạn mất thiết bị xác thực hai yếu tố.',

        'profile_title' => 'Thông tin hồ sơ',
        'profile_description' => 'Cập nhật thông tin hồ sơ và địa chỉ email của tài khoản.',
    ],

];
