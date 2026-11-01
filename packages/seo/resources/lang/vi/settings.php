<?php

declare(strict_types=1);

return [

    'menu' => 'SEO',
    'title' => 'Cài đặt SEO',
    'saved' => 'Đã lưu cài đặt SEO.',

    'tabs' => [
        'general' => 'Chung',
        'titles' => 'Tiêu đề & meta',
        'social' => 'Mạng xã hội',
        'schema' => 'Schema',
        'indexing' => 'Lập chỉ mục',
        'tracking' => 'Theo dõi & xác minh',
    ],

    'fields' => [
        'title_template' => 'Tiêu đề SEO',
        'description_template' => 'Mô tả meta',
        'meta_description' => 'Mô tả meta',
        'preview' => 'Xem trước kết quả tìm kiếm',
        'noindex' => 'Ẩn khỏi công cụ tìm kiếm (noindex)',
        'noindex_help' => 'Từng mục vẫn có thể ghi đè cài đặt này.',
        'sitemap' => 'Đưa vào sơ đồ trang XML',
        'schema_type' => 'Loại schema',
        'default_image' => 'Hình ảnh mạng xã hội mặc định',
        'variables' => 'Biến: :variables',
    ],

    'general' => [
        'identity' => 'Nhận diện trang web',
        'site_name' => 'Tên trang web',
        'tagline' => 'Khẩu hiệu',
        'separator' => 'Ký tự phân cách tiêu đề',
        'site_icon' => 'Ảnh đại diện trang web (biểu tượng trang)',
        'site_icon_help' => 'Ảnh vuông, tối thiểu 512 × 512 pixel. Dùng cho favicon, biểu tượng Apple touch, manifest ứng dụng web và làm logo mặc định.',
        'home' => 'Trang chủ',
        'visibility' => 'Hiển thị với công cụ tìm kiếm',
        'discourage' => 'Ngăn công cụ tìm kiếm lập chỉ mục trang web này',
        'discourage_help' => 'Thêm noindex, nofollow vào mọi trang và chặn trình thu thập trong robots.txt. Chỉ dùng cho trang thử nghiệm (staging).',
        'discourage_banner' => 'Công cụ tìm kiếm hiện đang được yêu cầu không lập chỉ mục trang web này.',
    ],

    'social' => [
        'default_image' => 'Hình ảnh Open Graph mặc định',
        'default_image_help' => 'Hiển thị khi trang được chia sẻ không có hình ảnh riêng. Kích thước tốt nhất là 1200 × 630 pixel.',
        'facebook_url' => 'URL trang Facebook',
        'facebook_app_id' => 'ID ứng dụng Facebook',
        'twitter_handle' => 'Tên người dùng X (Twitter)',
        'twitter_card' => 'Loại thẻ mặc định',
        'profiles' => 'Hồ sơ khác',
        'profiles_help' => 'Instagram, LinkedIn, YouTube, TikTok… được liệt kê trong schema dưới dạng `sameAs`.',
    ],

    'schema' => [
        'publisher' => 'Ai vận hành trang web này?',
        'entity' => 'Trang web này đại diện cho',
        'organization' => 'Một tổ chức',
        'person' => 'Một cá nhân',
        'name' => 'Tên',
        'logo' => 'Logo',
        'local_business' => 'Loại hình kinh doanh địa phương',
        'local_business_help' => 'Chỉ khi khách hàng có thể đến cửa hàng thực tế.',
        'opening_hours' => 'Giờ mở cửa',
        'website' => 'Trang web',
        'search_url' => 'URL tìm kiếm trên trang',
        'search_url_help' => 'Bật ô tìm kiếm trong kết quả Google. Phải chứa {search_term_string}.',
        'article_type' => 'Loại mặc định của bài viết blog',
    ],

    'indexing' => [
        'archives' => 'Ẩn các trang này khỏi công cụ tìm kiếm',
        'date_archives' => 'Lưu trữ theo ngày',
        'author_archives' => 'Lưu trữ theo tác giả',
        'search' => 'Kết quả tìm kiếm',
        'paginated' => 'Trang 2 trở đi của các trang lưu trữ',
        'empty_terms' => 'Chuyên mục và thẻ không có nội dung',
        'paths' => 'Đường dẫn cần ẩn',
        'paths_help' => 'Các mẫu URL được gắn noindex, ví dụ /cart* hoặc /account/*. Dùng * làm ký tự đại diện.',
        'pattern' => 'Mẫu đường dẫn',
        'nofollow' => 'Đồng thời nofollow',
    ],

    'tracking' => [
        'analytics' => 'Phân tích',
        'gtm' => 'ID vùng chứa Google Tag Manager',
        'gtm_help' => 'Được thêm vào thẻ head và ngay sau thẻ mở body của mọi trang.',
        'ga4' => 'ID đo lường Google Analytics 4',
        'ga4_help' => 'Để trống nếu Google Analytics đã được cài trong Tag Manager.',
        'verification' => 'Xác minh trang web',
        'verification_help' => 'Chỉ dán giá trị content của thẻ meta, không dán cả thẻ.',
        'custom_code' => 'Mã tùy chỉnh',
        'custom_code_warning' => 'Mã này được thêm nguyên trạng vào mọi trang và có thể chạy script. Chỉ dán mã từ nguồn bạn tin cậy.',
        'custom_code_locked' => 'Bạn cần quyền "Quản lý script SEO và mã tùy chỉnh" để thay đổi mã này.',
        'custom_head' => 'Trước </head>',
        'custom_body_open' => 'Sau <body>',
        'custom_body_close' => 'Trước </body>',
    ],

    'robots' => [
        'help' => 'Dòng "Sitemap:" với URL sơ đồ trang của bạn được thêm tự động.',
    ],

    'preview' => [
        'sample_title' => 'Tiêu đề bài viết hoặc sản phẩm của bạn',
        'sample_excerpt' => 'Tóm tắt ngắn của nội dung, như sẽ xuất hiện dưới tiêu đề trong kết quả tìm kiếm.',
        'sample_category' => 'Tin tức',
    ],

];
