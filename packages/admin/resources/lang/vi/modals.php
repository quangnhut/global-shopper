<?php

declare(strict_types=1);

return [

    'permissions' => [
        'new' => 'Quyền mới',
        'new_description' => 'Thêm quyền mới và gán trực tiếp cho vai trò này',
        'labels' => [
            'name' => 'Tên quyền (chữ thường)',
        ],
    ],

    'roles' => [
        'new' => 'Thêm vai trò mới',
        'new_description' => 'Thêm vai trò mới và gán quyền cho quản trị viên.',
        'labels' => [
            'name' => 'Tên (chữ thường)',
        ],
        'confirm_delete_msg' => 'Bạn có chắc muốn xóa vai trò này? Tất cả người dùng có vai trò này sẽ không thể thực hiện các thao tác mà vai trò này cho phép nữa',
    ],

    'attributes' => [
        'new_value' => 'Thêm giá trị mới cho :attribute',
        'key_description' => 'Khóa được dùng làm giá trị lưu trữ cho các biểu mẫu (option, radio, v.v.). Phải ở định dạng slug',
        'update_value' => 'Cập nhật giá trị cho :name',
    ],

    'inventories' => [
        'confirm_delete_msg' => 'Bạn có chắc muốn xóa kho hàng này? Toàn bộ dữ liệu sẽ bị xóa. Không thể hoàn tác thao tác này',
    ],

];
