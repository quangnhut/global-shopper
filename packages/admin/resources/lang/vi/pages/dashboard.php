<?php

declare(strict_types=1);

return [

    'menu' => 'Bảng điều khiển',
    'welcome_message' => 'Chào mừng đến với Shopper',
    'welcome_description' => 'Đây là những gì bạn cần để cửa hàng đi vào hoạt động.',

    'cards' => [
        'doc_title' => 'Tài liệu',
    ],

    'guide' => [
        'title' => 'Hướng dẫn thiết lập',
        'description' => 'Hoàn thành các bước sau để bắt đầu bán hàng.',
        'progress' => 'trên :total đã hoàn thành',
        'dismiss' => 'Ẩn',
        'footer_hint' => 'Bạn luôn có thể truy cập các cài đặt này sau.',

        'steps' => [
            'add_product' => [
                'title' => 'Thêm sản phẩm đầu tiên',
                'description' => 'Thêm sản phẩm với giá, hình ảnh và biến thể để bắt đầu xây dựng danh mục hàng hóa.',
                'action' => 'Thêm sản phẩm',
            ],
            'create_collection' => [
                'title' => 'Tạo bộ sưu tập',
                'description' => 'Sắp xếp sản phẩm vào các bộ sưu tập để khách hàng dễ dàng duyệt cửa hàng.',
                'action' => 'Tạo bộ sưu tập',
            ],
            'setup_zones' => [
                'title' => 'Thiết lập khu vực vận chuyển',
                'description' => 'Cấu hình khu vực vận chuyển để xác định nơi bạn giao hàng và chi phí.',
                'action' => 'Thiết lập vận chuyển',
            ],
            'setup_payments' => [
                'title' => 'Thiết lập phương thức thanh toán',
                'description' => 'Thêm phương thức thanh toán để khách hàng có thể thanh toán đơn hàng.',
                'action' => 'Thiết lập thanh toán',
            ],
            'setup_taxes' => [
                'title' => 'Cấu hình thuế',
                'description' => 'Thiết lập khu vực thuế và thuế suất để tự động tính thuế cho đơn hàng.',
                'action' => 'Cấu hình thuế',
            ],
        ],
    ],

    'stats' => [
        'revenue' => 'Tổng doanh thu',
        'products' => 'Tổng sản phẩm',
        'orders' => 'Tổng đơn hàng',
        'customers' => 'Tổng khách hàng',
        'vs_last_month' => 'so với tháng trước',
        'view_more' => 'Xem thêm',
    ],

    'chart' => [
        'heading' => 'Hiệu suất',
        'series_label' => 'Doanh thu',
    ],

    'recent_orders' => [
        'heading' => 'Đơn hàng gần đây',
        'view_all' => 'Xem tất cả',
        'empty' => 'Chưa có đơn hàng nào.',
    ],

    'top_products' => [
        'heading' => 'Sản phẩm bán chạy',
        'view_all' => 'Xem tất cả',
        'product' => 'Sản phẩm',
        'sales' => 'Đã bán',
        'reviews' => 'Đánh giá',
        'empty' => 'Chưa có lượt bán nào.',
    ],

    'addons' => [
        'title' => 'Mở rộng cửa hàng',
        'badge' => 'Tiện ích',
        'learn_more' => 'Tìm hiểu thêm',
        'configure' => 'Cấu hình đơn vị vận chuyển',

        'stripe' => [
            'title' => 'Stripe',
            'description' => 'Chấp nhận thẻ tín dụng, Apple Pay và Google Pay với Stripe.',
        ],
        'carriers' => [
            'title' => 'Đơn vị vận chuyển',
            'description' => 'Kết nối UPS, FedEx, USPS và nhiều hơn nữa để lấy phí vận chuyển theo thời gian thực.',
        ],
    ],

];
