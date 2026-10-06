<?php

declare(strict_types=1);

return [
    'NUM_SERIES_NOT_FOUND' => 'Không tìm thấy series đánh số.',
    'NUM_RESOURCE_NOT_FOUND' => 'Không tìm thấy Resource đánh số.',
    'NUM_INVALID_CONFIGURATION' => 'Cấu hình đánh số không hợp lệ.',
    'NUM_AUTHORIZATION_DENIED' => 'Không được phép cấu hình đánh số.',
    'NUM_ACTIVE_DEFAULT_CONFLICT' => 'Resource đã có active default numbering series.',
    'NUM_ISSUED_FIELD_IMMUTABLE' => 'Identity của numbering series đã phát hành không thể thay đổi.',
    'NUM_ISSUED_NO_HARD_DELETE' => 'Numbering series đã phát hành không thể hard-delete.',
    'NUM_PATTERN_VALIDATOR_REQUIRED' => 'Active numbering series yêu cầu pattern validator chuẩn.',
    'NUM_PATTERN_INVALID' => 'Pattern đánh số không hợp lệ.',
    'NUM_PATTERN_COLLISION' => 'Pattern đánh số xung đột với một series active khác.',
    'NUM_SCOPE_INVALID' => 'Scope hoặc reset policy của numbering không hợp lệ.',
    'NUM_SCOPE_CONTEXT_REQUIRED' => 'Scope này yêu cầu Evaluation Organization authoritative.',
    'NUM_SCOPE_RESOLUTION_FAILED' => 'Không thể resolve numbering scope authoritative.',
    'NUM_GROUP_ROOT_INVALID' => 'GROUP root canonical bị thiếu, inactive hoặc không duy nhất.',
    'NUM_COMPANY_CONTEXT_REQUIRED' => 'Numbering operation yêu cầu Company context canonical.',
    'NUM_BUSINESS_TIMEZONE_REQUIRED' => 'Numbering period yêu cầu business timezone đã cấu hình.',
    'NUM_BUSINESS_TIMEZONE_INVALID' => 'Business timezone đã cấu hình không hợp lệ.',
    'NUM_BUSINESS_TIMEZONE_RESOLUTION_FAILED' => 'Không thể resolve business timezone.',
    'NUM_PATTERN_METADATA_REQUIRED' => 'Cần parsed metadata của canonical pattern.',
];
