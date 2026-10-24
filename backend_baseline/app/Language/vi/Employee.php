<?php

declare(strict_types=1);

return [
    'EMP_MANAGER_SELF'              => 'Nhân viên không thể tự làm quản lý của chính mình.',
    'EMP_MANAGER_INACTIVE'          => 'Không thể chọn nhân viên không hoạt động làm quản lý.',
    'EMP_MANAGER_CYCLE'             => 'Quan hệ quản lý tạo thành một chu trình không hợp lệ.',
    'EMP_MANAGER_REFERENCE_INVALID' => 'Tham chiếu quản lý không hợp lệ.',
    'EMP_MANAGER_MUTATION_FAILED'   => 'Không thể cập nhật quan hệ quản lý. Vui lòng thử lại.',
    'EMPLOYEE_CODE_DUPLICATE'       => 'Mã Employee đã được sử dụng.',
    'EMPLOYEE_USER_DUPLICATE'       => 'User đã được liên kết với một Employee khác.',
    'EMPLOYEE_STATUS_INVALID'       => 'Trạng thái Employee không hợp lệ.',
    'EMPLOYEE_GENDER_INVALID'       => 'Gender của Employee không hợp lệ.',
    'EMP_PERSISTENCE_FAILED'        => 'Không thể lưu Employee.',
    'EMP_CONNECTION_INVALID'        => 'Kết nối cơ sở dữ liệu không hỗ trợ ghi Employee.',
    'EMP_UPDATE_EMPTY'              => 'Dữ liệu cập nhật Employee không được để trống.',
    'EMP_FIELD_UNSUPPORTED'         => 'Trường Employee không được hỗ trợ.',
    'EMP_FIELD_REQUIRED'            => 'Thiếu trường Employee bắt buộc.',
    'EMP_ASSIGNMENT_PERSISTENCE_FAILED' => 'Không thể lưu Employee Assignment.',
    'EMP_ASSIGNMENT_CONNECTION_INVALID' => 'Kết nối cơ sở dữ liệu không hỗ trợ ghi Employee Assignment.',
    'EMP_ASSIGNMENT_UPDATE_EMPTY'       => 'Dữ liệu cập nhật Employee Assignment không được để trống.',
    'EMP_ASSIGNMENT_FIELD_UNSUPPORTED'  => 'Trường Employee Assignment không được hỗ trợ.',
    'EMP_ASSIGNMENT_FIELD_REQUIRED'     => 'Thiếu trường Employee Assignment bắt buộc.',
    'EMP_ASSIGNMENT_REFERENCE_INVALID' => 'Tham chiếu Employee Assignment không hợp lệ.',
    'EMP_ASSIGNMENT_REFERENCE_INACTIVE' => 'Tham chiếu Employee Assignment không hoạt động.',
    'EMP_ASSIGNMENT_DATE_INVALID' => 'Khoảng ngày Employee Assignment không hợp lệ.',
    'EMP_PRIMARY_ASSIGNMENT_OVERLAP' => 'Primary Assignment bị chồng lấn với Primary Assignment đang có hiệu lực.',
    'EMP_ASSIGNMENT_NOT_FOUND' => 'Không tìm thấy Employee Assignment.',
    'EMP_ASSIGNMENT_EMPLOYEE_IMMUTABLE' => 'Không thể thay đổi Employee của Assignment.',
    'EMP_ASSIGNMENT_EVALUATION_ORGANIZATION_INVALID' => 'Evaluation Organization không hợp lệ.',
    'EMP_ASSIGNMENT_MUTATION_FAILED' => 'Không thể cập nhật Employee Assignment. Vui lòng thử lại.',
];
