<?php

declare(strict_types=1);

return [

    'menu' => 'Khách hàng',
    'single' => 'khách hàng',
    'title' => 'Quản lý đơn hàng & thông tin khách hàng',
    'content' => 'Đây là nơi bạn quản lý thông tin khách hàng và xem lịch sử mua hàng của họ.',

    'overview' => 'Tổng quan hồ sơ',
    'overview_description' => 'Dùng địa chỉ cố định nơi khách hàng có thể nhận thư.',
    'security_title' => 'Bảo mật',
    'security_description' => 'Nhập mật khẩu ngẫu nhiên mà người dùng này sẽ dùng để đăng nhập tài khoản.',
    'address_title' => 'Địa chỉ',
    'address_description' => 'Địa chỉ chính của khách hàng này. Địa chỉ này sẽ được đặt làm địa chỉ giao hàng mặc định.',
    'notification_title' => 'Thông báo',
    'notification_description' => 'Thông báo cho khách hàng về tài khoản của họ.',
    'marketing_email' => 'Khách hàng đồng ý nhận email tiếp thị.',
    'marketing_description' => 'Bạn nên xin phép khách hàng trước khi đăng ký họ nhận email tiếp thị (nếu có).',
    'send_credentials' => 'Gửi thông tin đăng nhập cho khách hàng.',
    'credential_description' => 'Một email chứa thông tin đăng nhập này sẽ được gửi đến khách hàng.',

    'period' => 'Khách hàng được :period',

    'modal' => [
        'title' => 'Lưu trữ khách hàng này',
        'description' => 'Bạn có chắc muốn vô hiệu hóa khách hàng này? Toàn bộ dữ liệu (đơn hàng & địa chỉ) sẽ bị xóa vĩnh viễn khỏi cửa hàng. Không thể hoàn tác thao tác này.',
        'success_message' => 'Bạn đã lưu trữ khách hàng này thành công, khách hàng không còn trong danh sách khách hàng.',
    ],

    'profile' => [
        'title' => 'Hồ sơ',
        'description' => 'Tất cả thông tin công khai của khách hàng có ở đây.',
        'account' => 'Tài khoản',
        'account_description' => 'Quản lý cách sử dụng thông tin trên tài khoản khách hàng.',
        'marketing' => 'Email tiếp thị',
        'two_factor' => 'Xác thực hai yếu tố',
    ],

    'addresses' => [
        'title' => 'Địa chỉ',
        'shipping' => 'Địa chỉ giao hàng',
        'billing' => 'Địa chỉ thanh toán',
        'default' => 'Địa chỉ mặc định',
        'customer' => 'Địa chỉ của khách hàng',
        'empty_text' => 'Khách hàng này chưa có địa chỉ giao hàng hoặc thanh toán.',
    ],

    'orders' => [
        'placed' => 'Ngày đặt hàng',
        'total' => 'Tổng cộng',
        'ship_to' => 'Giao đến',
        'order_number' => 'Đơn hàng :number',
        'details' => 'Chi tiết đơn hàng',
        'items' => 'Sản phẩm trong đơn',
        'view' => 'Xem đơn hàng',
        'empty_text' => 'Không tìm thấy đơn hàng nào...',
        'no_shipping' => 'Không có phương thức vận chuyển',
        'estimated' => 'Ngày giao hàng',
    ],

    'anonymize' => [
        'action' => 'Ẩn danh khách hàng',
        'title' => 'Ẩn danh khách hàng này',
        'description' => 'Thao tác này sẽ ẩn danh vĩnh viễn toàn bộ dữ liệu cá nhân của khách hàng (tên, email, điện thoại, địa chỉ). Lịch sử đơn hàng vẫn được giữ cho mục đích kế toán. Không thể hoàn tác thao tác này.',
        'confirm' => 'Có, ẩn danh',
        'success' => 'Đã ẩn danh khách hàng thành công.',
        'first_name' => 'Đã xóa',
        'last_name' => 'Khách hàng',
    ],

];
