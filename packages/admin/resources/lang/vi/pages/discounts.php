<?php

declare(strict_types=1);

return [

    'menu' => 'Giảm giá',
    'single' => 'mã giảm giá',
    'title' => 'Quản lý giảm giá và khuyến mãi',
    'description' => 'Tạo & quản lý mã giảm giá và khuyến mãi áp dụng khi thanh toán hoặc cho đơn hàng của khách.',

    'empty_message' => 'Không tìm thấy mã giảm giá nào...',
    'search' => 'Tìm mã giảm giá',
    'name_helptext' => 'Khách hàng sẽ nhập mã giảm giá này khi thanh toán.',
    'percentage' => 'Phần trăm',
    'percentage_description' => 'Giảm giá theo %',
    'fixed_amount' => 'Số tiền cố định',
    'fixed_amount_description' => 'Giảm giá theo số tiền',
    'configuration_description' => 'Mã giảm giá có hiệu lực ngay khi bạn bấm nút xuất bản và vẫn hoạt động nếu không bị thay đổi.',
    'condition_description' => 'Mã giảm giá áp dụng cho tất cả sản phẩm nếu không bị thay đổi.',
    'applies_to' => 'Áp dụng cho',
    'entire_order' => 'Toàn bộ đơn hàng',
    'specific_products' => 'Sản phẩm cụ thể',
    'select_products' => 'Chọn sản phẩm',
    'min_requirement' => 'Yêu cầu tối thiểu',
    'none' => 'Không có',
    'min_amount' => 'Giá trị mua tối thiểu (:currency)',
    'min_value' => 'Giá trị tối thiểu',
    'applies_only_selected' => 'Chỉ áp dụng cho các sản phẩm đã chọn.',
    'min_quantity' => 'Số lượng sản phẩm tối thiểu',
    'customer_eligibility' => 'Đối tượng khách hàng',
    'everyone' => 'Mọi người',
    'specific_customers' => 'Khách hàng cụ thể',
    'select_customers' => 'Chọn khách hàng',
    'usage_limits' => 'Giới hạn sử dụng',
    'usage_label' => 'Giới hạn tổng số lần mã giảm giá này có thể được sử dụng',
    'usage_label_description' => 'Giới hạn này áp dụng cho tất cả khách hàng, không tính riêng từng người.',
    'usage_value' => 'Số lần sử dụng tối đa',
    'limit_one_per_user' => 'Giới hạn mỗi khách hàng dùng một lần',
    'active_dates' => 'Thời gian hiệu lực',
    'active_dates_description' => 'Khoảng thời gian mã giảm giá có hiệu lực cho người dùng.',
    'start_date' => 'Ngày bắt đầu',
    'choose_start_date' => 'Chọn ngày bắt đầu',
    'end_date' => 'Ngày kết thúc',
    'choose_end_date' => 'Chọn ngày kết thúc',
    'empty_code' => 'Chưa nhập thông tin nào.',
    'count_items' => ':count sản phẩm',
    'min_purchase' => 'Mua tối thiểu',

    'modals' => [
        'stock_available' => 'Còn :stock',
        'add_products' => 'Thêm sản phẩm',
        'add_selected_products' => 'Thêm các sản phẩm đã chọn',
        'search_product' => 'Tìm sản phẩm theo tên',

        'add_customers' => 'Thêm khách hàng',
        'search_customer' => 'Tìm khách hàng theo tên',
        'add_selected_customers' => 'Thêm các khách hàng đã chọn',

        'remove' => [
            'title' => 'Xóa mã này',
            'description' => 'Bạn có chắc muốn xóa mã này? Toàn bộ dữ liệu sẽ bị xóa. Không thể hoàn tác thao tác này.',
            'success_message' => 'Đã xóa mã giảm giá thành công!',
        ],
    ],

    'active_today' => 'Có hiệu lực hôm nay',
    'active_from_today' => 'Có hiệu lực từ hôm nay',
    'active_from' => 'Có hiệu lực từ :date',
    'active_date' => 'Có hiệu lực :date',
    'active_from_to' => 'Có hiệu lực từ :start đến :end',
    'one_per_customer' => 'mỗi khách hàng một lần',

    'save' => 'Đã lưu mã giảm giá :code thành công!',
    'total_use' => 'Lượt sử dụng',

];
