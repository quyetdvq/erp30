<?php

declare(strict_types=1);

return [
    'PRICE_LIST_NOT_FOUND' => 'Không tìm thấy bảng giá.',
    'PRICE_LIST_AUTHORIZATION_DENIED' => 'Bạn không có quyền thực hiện thao tác này với bảng giá.',
    'PRICE_LIST_REQUIRED_FIELD' => 'Thiếu trường bắt buộc của bảng giá hoặc giá trị không hợp lệ.',
    'PRICE_LIST_INVALID_FIELD' => 'Trường bảng giá hoặc tham số truy vấn không được hỗ trợ.',
    'PRICE_LIST_STATUS_REQUIRES_SETSTATE' => 'Hãy dùng chức năng chuyển trạng thái để đổi trạng thái bảng giá.',
    'PRICE_LIST_INVALID_STATUS' => 'Trạng thái bảng giá được yêu cầu không hợp lệ.',
    'PRICE_LIST_INVALID_EFFECTIVE_INTERVAL' => 'Khoảng thời gian hiệu lực của bảng giá không hợp lệ.',
    'PRICE_LIST_INVALID_ORGANIZATION_SCOPE' => 'Bảng giá chỉ được thuộc tổ chức GROUP hoặc COMPANY.',
    'PRICE_LIST_INVALID_REFERENCE' => 'Dữ liệu tham chiếu bảng giá không hợp lệ.',
    'PRICE_LIST_INVALID_ITEM' => 'Sản phẩm hoặc đơn vị tính trong bảng giá không hợp lệ.',
    'PRICE_LIST_ITEM_DUPLICATE' => 'Cặp sản phẩm/đơn vị tính bị lặp trong bảng giá.',
    'PRICE_LIST_INVALID_PRICE' => 'Giá phải không âm và đúng định dạng DECIMAL(18,2).',
    'PRICE_LIST_INACTIVE_REFERENCE' => 'Tham chiếu mới hoặc bị thay đổi phải đang hoạt động.',
    'PRICE_LIST_DUPLICATE' => 'Mã bảng giá hoặc cặp sản phẩm/đơn vị tính đã tồn tại.',
    'PRICE_LIST_CONCURRENT_MODIFICATION' => 'Bảng giá đã bị thay đổi bởi một thao tác đồng thời.',
    'PRICE_LIST_SCOPE_TOO_COMPLEX' => 'Phạm vi phân quyền bảng giá quá lớn để biên dịch an toàn.',
    'PRICE_LIST_CONNECTION_INVALID' => 'Chức năng bảng giá yêu cầu các bảng InnoDB hỗ trợ giao dịch.',
    'PRICE_LIST_LOCK_FAILED' => 'Bảng giá đang được cập nhật, vui lòng thử lại.',
    'PRICE_LIST_MUTATION_FAILED' => 'Không thể lưu thay đổi bảng giá.',
];
