<?php

declare(strict_types=1);

return [
    'title' => 'Thanh toán',
    'contact' => 'Thông tin liên hệ',
    'optional' => 'không bắt buộc',
    'guest' => 'Bạn có thể đặt hàng không cần tài khoản. Đã có tài khoản?',
    'sign_in' => 'Đăng nhập',
    'note_placeholder' => 'Ví dụ: thời gian bạn muốn đến nhận hàng',
    'terms' => 'Tôi đồng ý với',
    'place_order' => 'Đặt hàng',
    'placing' => 'Đang đặt hàng…',
    'back_to_cart' => 'Quay lại giỏ hàng',
    'fields' => [
        'name' => 'Họ và tên',
        'phone' => 'Số điện thoại',
        'email' => 'Email',
        'note' => 'Ghi chú',
        'payment_method' => 'Phương thức thanh toán',
        'terms' => 'điều khoản',
    ],
    'pickup' => [
        'title' => 'Nhận hàng tại văn phòng',
        'intro' => 'Chúng tôi không giao hàng. Bạn nhận hàng tại văn phòng khi đơn hàng sẵn sàng.',
        'hours' => 'Giờ làm việc: :hours',
    ],
    'office' => [
        'instructions' => 'Vui lòng đến văn phòng, đọc mã đơn hàng :number và thanh toán bằng tiền mặt hoặc thẻ khi nhận hàng.',
    ],
    'bank' => [
        'bank' => 'Ngân hàng',
        'account_number' => 'Số tài khoản',
        'account_name' => 'Chủ tài khoản',
        'amount' => 'Số tiền',
        'content' => 'Nội dung chuyển khoản',
        'confirm' => 'Chúng tôi sẽ xác nhận khi nhận được tiền. Vui lòng ghi đúng nội dung chuyển khoản.',
        'missing' => 'Chúng tôi sẽ gọi cho bạn để gửi thông tin chuyển khoản. Nội dung chuyển khoản: :number.',
    ],
    'onepay' => [
        'paid' => 'Đơn hàng đã được thanh toán qua OnePay.',
        'pending' => 'Đơn hàng chưa được thanh toán.',
        'pay_again' => 'Thanh toán qua OnePay',
    ],
    'thanks' => [
        'title' => 'Cảm ơn bạn đã đặt hàng!',
        'number' => 'Mã đơn hàng:',
        'contact_soon' => 'Chúng tôi sẽ liên hệ qua số :phone khi đơn hàng sẵn sàng.',
        'items' => 'Sản phẩm',
        'qr_alt' => 'Mã VietQR chuyển khoản cho đơn :number',
    ],
    'errors' => [
        'payment_method' => 'Phương thức thanh toán này không còn khả dụng.',
        'no_payment_method' => 'Hiện chưa có phương thức thanh toán nào. Vui lòng gọi hotline để đặt hàng.',
        'cart_changed' => 'Giỏ hàng đã thay đổi. Vui lòng kiểm tra lại trước khi đặt hàng.',
        'empty' => 'Giỏ hàng của bạn đang trống.',
        'stock' => 'Một sản phẩm vừa hết hàng. Vui lòng kiểm tra lại giỏ hàng.',
        'throttle' => 'Bạn thao tác quá nhanh. Vui lòng thử lại sau :seconds giây.',
    ],
    'validation' => [
        'required' => 'Vui lòng nhập :attribute.',
        'min' => ':attribute phải có ít nhất :min ký tự.',
        'max' => ':attribute không được quá :max ký tự.',
        'email' => 'Email không hợp lệ.',
        'phone' => 'Số điện thoại không hợp lệ (VD: 0901 234 567).',
        'in' => 'Vui lòng chọn :attribute.',
        'accepted' => 'Vui lòng đồng ý với điều khoản để đặt hàng.',
    ],
];
