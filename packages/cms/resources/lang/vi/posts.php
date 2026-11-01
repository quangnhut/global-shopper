<?php

declare(strict_types=1);

return [

    'add_new' => 'Thêm mới',
    'add_new_post' => 'Thêm bài viết mới',
    'add_new_page' => 'Thêm trang mới',
    'edit_post' => 'Sửa bài viết',
    'edit_page' => 'Sửa trang',
    'no_title' => '(không có tiêu đề)',

    'tabs' => [
        'all' => 'Tất cả',
        'mine' => 'Của tôi',
        'trash' => 'Thùng rác',
    ],

    'columns' => [
        'title' => 'Tiêu đề',
        'author' => 'Tác giả',
        'categories' => 'Chuyên mục',
        'tags' => 'Thẻ',
        'date' => 'Ngày',
        'parent' => 'Trang cha',
    ],

    'date_labels' => [
        'published' => 'Đã xuất bản',
        'scheduled' => 'Đã lên lịch',
        'modified' => 'Sửa đổi lần cuối',
    ],

    'filters' => [
        'month' => 'Ngày',
        'all_dates' => 'Tất cả các ngày',
        'category' => 'Chuyên mục',
        'author' => 'Tác giả',
    ],

    'actions' => [
        'quick_edit' => 'Sửa nhanh',
        'bulk_edit' => 'Sửa',
        'trash' => 'Chuyển vào thùng rác',
        'restore' => 'Khôi phục',
        'delete_permanently' => 'Xóa vĩnh viễn',
        'empty_trash' => 'Dọn sạch thùng rác',
        'empty_trash_confirm' => 'Mọi mục trong thùng rác sẽ bị xóa vĩnh viễn. Không thể hoàn tác thao tác này.',
        'save_draft' => 'Lưu nháp',
        'save_as_pending' => 'Lưu ở trạng thái chờ duyệt',
        'publish' => 'Xuất bản',
        'schedule' => 'Lên lịch',
        'update' => 'Cập nhật',
        'submit_for_review' => 'Gửi duyệt',
        'preview' => 'Xem trước',
        'no_change' => '— Không thay đổi —',
    ],

    'notifications' => [
        'trashed' => 'Đã chuyển :count mục vào thùng rác.|Đã chuyển :count mục vào thùng rác.',
        'restored' => 'Đã khôi phục :count mục từ thùng rác.|Đã khôi phục :count mục từ thùng rác.',
        'deleted' => 'Đã xóa vĩnh viễn :count mục.|Đã xóa vĩnh viễn :count mục.',
        'updated' => 'Đã cập nhật :count mục.|Đã cập nhật :count mục.',
        'saved_draft' => 'Đã lưu bản nháp.',
        'published' => 'Đã xuất bản.',
        'scheduled' => 'Đã lên lịch vào :date.',
        'updated_single' => 'Đã cập nhật.',
        'submitted' => 'Đã gửi duyệt.',
        'skipped' => 'Đã bỏ qua :count mục vì bạn không có quyền thay đổi.|Đã bỏ qua :count mục vì bạn không có quyền thay đổi.',
    ],

    'form' => [
        'title' => 'Tiêu đề',
        'slug' => 'Đường dẫn (slug)',
        'slug_help' => 'Phần cuối của URL. Để trống để tạo từ tiêu đề.',
        'content' => 'Nội dung',
        'excerpt' => 'Tóm tắt',
        'excerpt_help' => 'Bản tóm tắt nội dung do bạn tự viết (không bắt buộc).',
        'publish' => 'Xuất bản',
        'status' => 'Trạng thái',
        'visibility' => 'Hiển thị',
        'password' => 'Mật khẩu',
        'password_help' => 'Để trống để giữ mật khẩu hiện tại.',
        'published_at' => 'Ngày xuất bản',
        'published_at_help' => 'Để trống để xuất bản ngay. Chọn ngày trong tương lai để lên lịch xuất bản.',
        'last_edited' => 'Sửa lần cuối bởi :name vào :date.',
        'trashed_notice' => 'Mục này đang ở thùng rác. Hãy khôi phục để chỉnh sửa.',
        'categories' => 'Chuyên mục',
        'primary_category' => 'Chuyên mục chính',
        'primary_category_help' => 'Dùng trong URL và breadcrumb khi bài viết có nhiều chuyên mục.',
        'tags' => 'Thẻ',
        'tags_help' => 'Phân tách các thẻ bằng dấu phẩy hoặc phím Enter.',
        'page_attributes' => 'Thuộc tính trang',
        'parent' => 'Trang cha',
        'no_parent' => '(không có trang cha)',
        'template' => 'Giao diện mẫu',
        'menu_order' => 'Thứ tự',
        'featured_image' => 'Ảnh đại diện',
        'featured_image_help' => 'Chọn ảnh từ thư viện tải lên hoặc tải ảnh mới lên.',
        'upload_image' => 'Tải ảnh lên',
        'author' => 'Tác giả',
        'unsaved_changes' => 'Bạn có thay đổi chưa lưu. Vẫn rời khỏi trang này?',
    ],

    'dashboard' => [
        'at_a_glance' => 'Tổng quan',
        'recent_drafts' => 'Bản nháp gần đây của bạn',
        'no_drafts' => 'Chưa có bản nháp nào.',
        'published_count' => ':count đã xuất bản trong :type|:count đã xuất bản trong :type',
        'drafts_count' => ':count bản nháp|:count bản nháp',
        'pending_count' => ':count chờ duyệt|:count chờ duyệt',
    ],

    'media' => [
        'add_media' => 'Thêm media',
        'insert' => 'Chèn vào bài viết',
        'file' => 'Tệp',
        'alt' => 'Văn bản thay thế',
        'alt_help' => 'Mô tả hình ảnh cho trình đọc màn hình và công cụ tìm kiếm.',
        'align' => 'Căn chỉnh',
        'align_none' => 'Không',
        'align_left' => 'Trái',
        'align_center' => 'Giữa',
        'align_right' => 'Phải',
    ],

    'preview' => [
        'title' => 'Xem trước',
        'notice' => 'Xem trước phiên bản chưa xuất bản. Liên kết này hết hạn :time.',
    ],

];
