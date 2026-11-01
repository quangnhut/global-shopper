<?php

declare(strict_types=1);

return [

    'title' => 'Đường dẫn tĩnh',
    'description' => 'Chọn cấu trúc URL cho bài viết, trang, chuyên mục và thẻ.',
    'common' => 'Cài đặt chung',
    'structures' => [
        'plain' => 'Đơn giản',
        'day_and_name' => 'Ngày và tên',
        'month_and_name' => 'Tháng và tên',
        'numeric' => 'Dạng số',
        'post_name' => 'Tên bài viết',
        'custom' => 'Cấu trúc tùy chỉnh',
    ],
    'custom_help' => 'Các thẻ có sẵn: :tags',
    'optional' => 'Tùy chọn',
    'optional_help' => 'Thay đổi tiền tố URL của chuyên mục và thẻ. Để trống để dùng mặc định "category" và "tag".',
    'category_base' => 'Tiền tố chuyên mục',
    'tag_base' => 'Tiền tố thẻ',
    'reading' => 'Trang chủ của bạn hiển thị',
    'front_page' => 'Trang chủ',
    'posts_page' => 'Trang bài viết',
    'latest_posts' => 'Các bài viết mới nhất',
    'saved' => 'Đã lưu cài đặt đường dẫn tĩnh.',
    'example' => 'Ví dụ: :url',

    'errors' => [
        'unknown_tag' => 'Cấu trúc chứa thẻ không xác định.',
        'needs_identifier' => 'Cấu trúc phải chứa %postname% hoặc %post_id%.',
        'reserved' => 'Cấu trúc không được bắt đầu bằng đường dẫn mà ứng dụng đang dùng.',
        'base_reserved' => 'Tiền tố này đã được ứng dụng sử dụng.',
    ],

    'redirects' => [
        'description' => 'Chuyển hướng khách truy cập và công cụ tìm kiếm từ URL cũ sang URL mới.',
        'title' => 'Chuyển hướng',
        'single' => 'chuyển hướng',
        'create' => 'Thêm chuyển hướng',
        'from' => 'Từ',
        'to' => 'Đến',
        'status' => 'Loại',
        'hits' => 'Lượt truy cập',
        'last_hit' => 'Truy cập gần nhất',
        'source' => 'Nguồn',
        'auto' => 'Tự động',
        'manual' => 'Thủ công',
        'gone' => 'Đã xóa (410)',
        'loop' => 'Chuyển hướng này sẽ tạo ra vòng lặp.',
        'same' => 'Một đường dẫn không thể chuyển hướng đến chính nó.',
    ],

];
