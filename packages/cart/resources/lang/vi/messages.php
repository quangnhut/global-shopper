<?php

declare(strict_types=1);

return [

    'exceptions' => [
        'cart_completed' => 'Giỏ hàng đã được hoàn tất.',
        'cart_not_found' => 'Không tìm thấy giỏ hàng.',
        'insufficient_stock' => 'Sản phẩm này không đủ tồn kho.',
    ],

    'discount' => [
        'not_found' => 'Không tìm thấy mã giảm giá.',
        'not_active' => 'Mã giảm giá chưa được kích hoạt.',
        'not_started' => 'Mã giảm giá chưa bắt đầu.',
        'expired' => 'Mã giảm giá đã hết hạn.',
        'usage_limit_reached' => 'Mã giảm giá đã đạt giới hạn sử dụng.',
        'already_used' => 'Khách hàng này đã sử dụng mã giảm giá.',
        'requires_login' => 'Mã giảm giá yêu cầu khách hàng đăng nhập.',
        'customer_not_eligible' => 'Khách hàng không đủ điều kiện dùng mã giảm giá này.',
        'not_available_in_zone' => 'Mã giảm giá không áp dụng cho khu vực này.',
        'min_amount_not_reached' => 'Chưa đạt giá trị mua tối thiểu.',
        'min_quantity_not_reached' => 'Chưa đạt số lượng tối thiểu.',
        'invalid_value' => 'Giá trị giảm giá phải lớn hơn 0.',
        'invalid_percentage' => 'Giảm giá theo phần trăm không được vượt quá 100%.',
    ],

];
