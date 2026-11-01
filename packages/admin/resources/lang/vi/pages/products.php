<?php

declare(strict_types=1);

return [

    'menu' => 'Sản phẩm',
    'single' => 'sản phẩm',
    'title' => 'Quản lý danh mục hàng hóa',
    'content' => 'Tiến gần hơn đến đơn hàng đầu tiên bằng cách thêm và quản lý sản phẩm.',
    'about_pricing' => 'Về cách hiển thị giá',
    'about_pricing_content' => 'Mặc định mọi giá được tính theo đơn vị nhỏ nhất (cent). Để lưu 10€ (hoặc 10$) bạn phải nhập 1000 để định dạng tiền tệ hiển thị đúng. Với tiền tệ không có số lẻ như VND, nhập đúng số tiền.',

    'amount_price_help_text' => 'Giá bán, trước khi giảm giá.',
    'compare_price_help_text' => 'Giá bán đề xuất, để so sánh với giá bán. Giá này thường cao hơn',
    'cost_per_items_help_text' => 'Giá sản xuất gốc. Khách hàng sẽ không thấy giá này',
    'safety_security_help_text' => 'Tồn kho an toàn là mức tồn kho giới hạn, sẽ cảnh báo bạn khi sản phẩm sắp hết hàng.',
    'quantity_inventory' => 'Số lượng tồn kho',
    'manage_inventories' => 'Quản lý kho hàng',
    'inventory_name' => 'Tên kho hàng',
    'product_can_returned' => 'Sản phẩm này có thể đổi trả',
    'product_can_returned_help_text' => 'Người dùng có thể trả lại sản phẩm này nếu có vấn đề hoặc không hài lòng.',
    'product_shipped' => 'Sản phẩm này sẽ được vận chuyển',
    'product_shipped_help_text' => 'Hãy điền đầy đủ thông tin liên quan đến việc vận chuyển sản phẩm.',
    'general' => 'Thông tin sản phẩm',
    'status' => 'Tình trạng sản phẩm',
    'featured_help_text' => 'Sản phẩm này sẽ được đánh dấu là nổi bật.',
    'visible_help_text' => 'Sản phẩm này sẽ bị ẩn khỏi mọi kênh bán hàng.',
    'availability_description' => 'Chỉ định ngày xuất bản để lên lịch hiển thị sản phẩm trên cửa hàng.',
    'type' => 'Loại sản phẩm',
    'product_type' => 'Đặt làm loại sản phẩm mặc định',
    'product_type_helpText' => 'Cấu hình này sẽ được lưu cho các sản phẩm bạn tạo tiếp theo.',
    'product_associations' => 'Liên kết',
    'related_products' => 'Sản phẩm liên quan',
    'quantity_available' => 'Số lượng có sẵn',
    'current_qty_inventory' => 'Số lượng hiện tại trong kho này',
    'stock_inventory_heading' => 'Tồn kho & kho hàng',
    'stock_inventory_description' => 'Cấu hình kho hàng và tồn kho cho :item này',
    'files_helpText' => 'Thêm các tệp mà khách hàng có thể tải xuống khi mua sản phẩm này.',
    'images_helpText' => 'Thêm hình ảnh cho sản phẩm.',
    'variant_images_helpText' => 'Thêm hình ảnh cho biến thể.',
    'thumbnail_helpText' => 'Dùng để đại diện cho sản phẩm khi thanh toán, chia sẻ mạng xã hội và nhiều nơi khác.',
    'weight_dimension' => 'Khối lượng và kích thước',
    'weight_dimension_help_text' => 'Dùng để tính phí vận chuyển khi thanh toán và ghi nhãn giá khi xử lý đơn hàng.',
    'external_id_description' => 'Mã định danh gốc của sản phẩm từ nhà cung cấp bên ngoài',
    'allow_backorder' => 'Cho phép đặt hàng trước khi có hàng',

    'modals' => [
        'title' => 'Xóa :item này',
        'message' => 'Bạn có chắc muốn xóa sản phẩm này? Mọi thông tin liên quan đến sản phẩm sẽ bị xóa.',

        'variants' => [
            'title' => 'Quản lý tồn kho cho biến thể này',
            'select' => 'Chọn kho hàng',
            'add' => 'Thêm biến thể mới',
            'options' => [
                'title' => 'Thuộc tính biến thể',
                'description' => 'Chọn các tùy chọn thuộc tính cho biến thể này.',
            ],
        ],
    ],

    'variants' => [
        'menu' => 'Biến thể',
        'single' => 'biến thể',
        'title' => 'Biến thể sản phẩm',
        'description' => 'Tất cả biến thể của sản phẩm. Mỗi biến thể có thể có tồn kho và giá riêng.',
        'add' => 'Thêm biến thể',
        'generate' => 'Tạo biến thể',
        'generate_description' => 'Sản phẩm được tạo theo các thuộc tính bạn đã chọn',
        'variant_title' => 'Biến thể ~ :name',
        'empty' => 'Không tìm thấy biến thể nào',
        'search_label' => 'Tìm biến thể',
        'search_placeholder' => 'Tìm biến thể sản phẩm',
        'variant_information' => 'Thông tin biến thể',
    ],

    'reviews' => [
        'single' => 'đánh giá',
        'title' => 'Đánh giá của khách hàng',
        'description' => 'Đây là nơi bạn xem các đánh giá của khách hàng và điểm xếp hạng dành cho sản phẩm.',
        'view' => 'Đánh giá cho :product',
        'published' => 'Đã xuất bản',
        'pending' => 'Chờ duyệt',
        'approved' => 'Đánh giá đã duyệt',
        'is_recommended' => 'Đánh giá được đề xuất',
        'approved_status' => 'Trạng thái duyệt',
        'approved_message' => 'Đã cập nhật trạng thái duyệt của đánh giá!',

        'subtitle' => 'Đánh giá cho sản phẩm này.',
        'reviewer' => 'Người đánh giá',
        'review' => 'Đánh giá',
        'review_content' => 'Nội dung',
        'status' => 'Trạng thái',
        'rating' => 'Xếp hạng',
        'star' => 'sao',
        'stars' => 'sao',

        'modal' => [
            'title' => 'Xóa đánh giá',
            'description' => 'Bạn có chắc muốn xóa đánh giá này? Đánh giá sẽ không thể khôi phục được nữa.',
            'success_message' => 'Đã xóa đánh giá thành công!',
        ],
    ],

    'attributes' => [
        'title' => 'Thuộc tính sản phẩm',
        'description' => 'Tất cả thuộc tính gắn với sản phẩm này.',
        'choose' => 'Chọn thuộc tính',
        'empty_title' => 'Không có thuộc tính nào được bật',
        'empty_values' => 'Các thuộc tính gắn với sản phẩm này được liệt kê ở đây.',

        'session' => [
            'delete' => 'Đã gỡ thuộc tính',
            'delete_message' => 'Bạn đã gỡ thuộc tính này khỏi sản phẩm thành công!',
            'delete_value' => 'Đã gỡ giá trị thuộc tính',
            'delete_value_message' => 'Bạn đã gỡ giá trị của thuộc tính này thành công!',
            'added' => 'Đã thêm thuộc tính',
            'added_message' => 'Bạn đã thêm thuộc tính cho sản phẩm này thành công!',
        ],
    ],

    'inventory' => [
        'title' => 'Thuộc tính kho hàng',
        'description' => 'Các trường liên quan đến quản lý tồn kho trong cửa hàng.',
        'stock_title' => 'Quản lý tồn kho',
        'stock_description' => 'Quản lý tồn kho trong các kho hàng khác nhau.',
        'empty' => 'Chưa có điều chỉnh nào cho kho hàng.',
        'movement' => 'Biến động số lượng',
        'initial' => 'Tồn kho ban đầu',
        'add' => 'Thêm thủ công',
        'remove' => 'Bớt thủ công',
    ],

    'shipping' => [
        'description' => 'Thông tin về việc đổi trả sản phẩm hoặc xác định sản phẩm có thể được giao đến khách hàng hay không.',
        'package_dimension' => 'Kích thước kiện hàng',
        'package_dimension_description' => 'Tính thêm phí vận chuyển dựa trên kích thước kiện hàng tại đây.',
    ],

    'related' => [
        'title' => 'Sản phẩm tương tự',
        'description' => 'Tất cả sản phẩm có thể xem là tương tự hoặc bổ trợ cho sản phẩm của bạn.',
        'empty' => 'Không tìm thấy sản phẩm tương tự',
        'add_content' => 'Hãy bắt đầu bằng cách thêm một sản phẩm liên quan cho sản phẩm của bạn.',

        'modal' => [
            'title' => 'Thêm sản phẩm tương tự',
            'search' => 'Tìm sản phẩm',
            'search_placeholder' => 'Tìm sản phẩm theo tên',
            'action' => 'Thêm các sản phẩm đã chọn',
            'success_message' => 'Đã thêm sản phẩm được chọn',
            'no_results' => 'Không tìm thấy sản phẩm nào',
        ],
    ],

    'notifications' => [
        'files_update' => 'Đã cập nhật tệp sản phẩm!',
        'media_update' => 'Đã cập nhật media sản phẩm!',
        'replicated' => 'Đã nhân bản sản phẩm!',
        'stock_update' => 'Đã cập nhật tồn kho sản phẩm thành công!',
        'seo_update' => 'Đã cập nhật SEO sản phẩm thành công!',
        'shipping_update' => 'Đã cập nhật vận chuyển sản phẩm thành công!',
        'variation_generate' => 'Đã lưu các biến thể sản phẩm thành công',
        'variation_create' => 'Đã thêm biến thể sản phẩm thành công!',
        'variation_delete' => 'Đã xóa biến thể thành công!',
        'variation_update' => 'Đã cập nhật biến thể thành công!',
        'related_added' => 'Đã thêm sản phẩm vào danh sách sản phẩm liên quan!',
        'remove_related' => 'Đã xóa sản phẩm khỏi danh sách sản phẩm liên quan!',
        'manage_pricing' => 'Đã cập nhật giá sản phẩm!',
        'variant_already_exists' => 'Biến thể này đã tồn tại!',
    ],

    'pricing' => [
        'title' => 'Giá sản phẩm',
        'description' => 'Các mức giá khác nhau của sản phẩm, tùy theo các loại tiền tệ có trong cửa hàng.',
        'add' => 'Thêm giá mới',
        'empty' => 'Chưa thêm giá cho sản phẩm',
    ],

];
