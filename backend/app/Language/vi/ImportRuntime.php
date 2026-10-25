<?php

declare(strict_types=1);

return [
    'PRODUCT_IMPORT_AUTHORIZATION_DENIED'=>'Bạn không có quyền nhập sản phẩm.',
    'IMPORT_JOB_AUTHORIZATION_DENIED'=>'Bạn không có quyền xem lịch sử nhập dữ liệu.',
    'IMPORT_JOB_NOT_FOUND'=>'Không tìm thấy job nhập dữ liệu.',
    'IMPORT_FILE_REQUIRED'=>'Vui lòng chọn tệp CSV hoặc XLSX.',
    'IMPORT_FILE_INVALID'=>'Tệp không hợp lệ hoặc chứa nội dung không an toàn.',
    'IMPORT_FILE_TOO_LARGE'=>'Dung lượng tệp nhập tối đa 2 MiB.',
    'IMPORT_M8_FILE_LIMIT_EXCEEDED'=>'Dung lượng tệp nhập tối đa 2 MiB.',
    'IMPORT_ROW_LIMIT_EXCEEDED'=>'Mỗi tệp nhập tối đa 1.000 dòng không rỗng.',
    'IMPORT_M8_ROW_LIMIT_EXCEEDED'=>'Mỗi tệp nhập tối đa 1.000 dòng không rỗng.',
    'IMPORT_INVALID_TEMPLATE'=>'Tên cột nhập không hợp lệ hoặc bị trùng.',
    'IMPORT_INVALID_MODE'=>'Chọn CREATE_ONLY, UPDATE_ONLY hoặc UPSERT.',
    'IMPORT_INVALID_QUERY'=>'Bộ lọc hoặc phân trang nhập liệu không hợp lệ.',
    'IMPORT_SOURCE_KEY_REQUIRED'=>'Dòng sản phẩm phải có source_key.',
    'IMPORT_DUPLICATE_SOURCE_KEY'=>'source_key bị trùng trong tệp nhập.',
    'IMPORT_DUPLICATE_PRODUCT_CODE'=>'Mã sản phẩm bị trùng trong tệp nhập.',
    'IMPORT_DEPENDENCY_CYCLE'=>'Quan hệ cha-con trong tệp tạo thành chu trình.',
    'IMPORT_DEPENDENCY_INVALID'=>'Không tìm thấy parent_source_key trong tệp nhập.',
    'IMPORT_INVALID_RECORD_TYPE'=>'record_type không được hỗ trợ.',
    'IMPORT_REQUIRED_FIELD'=>'Thiếu trường bắt buộc trong dòng nhập.',
    'IMPORT_STATUS_REQUIRES_SETSTATE'=>'Phải đổi trạng thái sản phẩm qua quy trình duyệt được cấp quyền.',
    'IMPORT_REFERENCE_AMBIGUOUS'=>'Chỉ dùng source_key hoặc mã, không dùng cả hai.',
    'IMPORT_REFERENCE_INVALID'=>'Phải cung cấp đúng một tham chiếu source_key hoặc mã.',
    'IMPORT_REFERENCE_NOT_FOUND'=>'Không tìm thấy sản phẩm hoặc đơn vị liên quan.',
    'IMPORT_DEPENDENCY_FAILED'=>'Sản phẩm được tham chiếu chưa nhập thành công.',
    'IMPORT_TARGET_EXISTS'=>'Dữ liệu đã tồn tại, không thể CREATE_ONLY.',
    'IMPORT_TARGET_NOT_FOUND'=>'Không tìm thấy dữ liệu để UPDATE_ONLY.',
    'IMPORT_EMPTY_UPDATE'=>'Dòng nhập không có nội dung cập nhật.',
];
