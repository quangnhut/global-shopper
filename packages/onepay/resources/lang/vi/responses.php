<?php

declare(strict_types=1);

// vpc_TxnResponseCode of the OnePay domestic gateway.
return [
    '0' => 'Giao dịch thành công.',
    '1' => 'Ngân hàng từ chối giao dịch.',
    '3' => 'Mã đơn vị không tồn tại.',
    '4' => 'Không đúng access code.',
    '5' => 'Số tiền không hợp lệ.',
    '6' => 'Mã tiền tệ không tồn tại.',
    '7' => 'Lỗi không xác định.',
    '8' => 'Số thẻ không đúng.',
    '9' => 'Tên chủ thẻ không đúng.',
    '10' => 'Thẻ hết hạn hoặc bị khóa.',
    '11' => 'Thẻ chưa đăng ký dịch vụ thanh toán trực tuyến.',
    '12' => 'Ngày phát hành hoặc hết hạn không đúng.',
    '13' => 'Vượt quá hạn mức thanh toán.',
    '21' => 'Số dư không đủ để thanh toán.',
    '22' => 'Thông tin tài khoản không đúng.',
    '23' => 'Tài khoản bị khóa.',
    '24' => 'Thông tin thẻ không đúng.',
    '25' => 'Mã OTP không đúng.',
    '99' => 'Bạn đã hủy giao dịch.',
    '253' => 'Quá thời gian thanh toán.',
    'unknown' => 'Giao dịch không thành công (mã :code).',
];
