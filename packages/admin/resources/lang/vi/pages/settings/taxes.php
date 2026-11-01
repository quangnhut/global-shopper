<?php

declare(strict_types=1);

return [

    'title' => 'Thuế',
    'single' => 'Khu vực thuế',
    'description' => 'Quản lý khu vực thuế, thuế suất và cách tính thuế cho cửa hàng.',
    'add_action' => 'Thêm khu vực thuế',
    'empty_heading' => 'Không có khu vực thuế',
    'empty_detail_heading' => 'Chưa chọn khu vực thuế',
    'empty_detail_description' => 'Chọn một khu vực thuế để xem chi tiết và thuế suất.',
    'inclusive' => 'Đã gồm thuế',
    'exclusive' => 'Chưa gồm thuế',
    'inclusive_help' => 'Bật để giá đã bao gồm thuế kiểu VAT (ví dụ: châu Âu, châu Phi, Việt Nam).',
    'tax_behavior' => 'Cách tính thuế',
    'provider' => 'Nhà cung cấp thuế',
    'system_default' => 'Hệ thống (mặc định)',
    'province_code' => 'Mã tỉnh / bang',
    'province_code_help' => 'Mã đơn vị hành chính ISO 3166-2 (ví dụ: US-CA, FR-IDF, VN-SG).',
    'name_help' => 'Tên hiển thị tùy chọn cho khu vực này (ví dụ: California, TP. Hồ Chí Minh).',

    'rates' => [
        'title' => 'Thuế suất',
        'add' => 'Thêm thuế suất',
        'add_heading' => 'Thuế suất cho :name',
        'update' => 'Cập nhật :name',
        'rate' => 'Thuế suất',
        'empty_heading' => 'Chưa cấu hình thuế suất',
        'default_help' => 'Dùng thuế suất này khi không có ghi đè riêng cho sản phẩm.',
        'combinable' => 'Có thể cộng dồn',
        'combinable_help' => 'Cho phép thuế suất này cộng dồn với thuế suất của khu vực cha.',
    ],

    'overrides' => [
        'add' => 'Tạo ghi đè',
        'add_heading' => 'Ghi đè thuế suất cho :name',
        'update' => 'Cập nhật ghi đè :name',
        'description' => 'Ghi đè áp dụng thuế suất khác cho sản phẩm, loại sản phẩm hoặc danh mục cụ thể.',
        'targets' => 'Đối tượng',
        'targets_help' => 'Chọn sản phẩm, loại sản phẩm hoặc danh mục mà ghi đè này áp dụng.',
        'target_type' => 'Loại đối tượng',
        'target_value' => 'Giá trị đối tượng',
        'add_target' => 'Thêm đối tượng',
        'product_types' => 'Loại sản phẩm',
        'products' => 'Sản phẩm',
        'categories' => 'Danh mục',
    ],

];
