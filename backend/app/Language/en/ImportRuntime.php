<?php

declare(strict_types=1);

return [
    'PRODUCT_IMPORT_AUTHORIZATION_DENIED'=>'You are not authorized to import Product data.',
    'IMPORT_JOB_AUTHORIZATION_DENIED'=>'You cannot view these import jobs.',
    'IMPORT_JOB_NOT_FOUND'=>'Import job not found.',
    'IMPORT_FILE_REQUIRED'=>'Choose a CSV or XLSX import file.',
    'IMPORT_FILE_INVALID'=>'The file is invalid or contains unsafe content.',
    'IMPORT_FILE_TOO_LARGE'=>'Maximum import file size is 2 MiB.',
    'IMPORT_M8_FILE_LIMIT_EXCEEDED'=>'Maximum import file size is 2 MiB.',
    'IMPORT_ROW_LIMIT_EXCEEDED'=>'Maximum import size is 1,000 nonempty rows.',
    'IMPORT_M8_ROW_LIMIT_EXCEEDED'=>'Maximum import size is 1,000 nonempty rows.',
    'IMPORT_INVALID_TEMPLATE'=>'Invalid or duplicated import column names.',
    'IMPORT_INVALID_MODE'=>'Select CREATE_ONLY, UPDATE_ONLY or UPSERT.',
    'IMPORT_INVALID_QUERY'=>'Invalid import list filter or pagination.',
    'IMPORT_SOURCE_KEY_REQUIRED'=>'Each Product row requires source_key.',
    'IMPORT_DUPLICATE_SOURCE_KEY'=>'Duplicate source_key in this file.',
    'IMPORT_DUPLICATE_PRODUCT_CODE'=>'Duplicate Product code in this file.',
    'IMPORT_DEPENDENCY_CYCLE'=>'Product parent references form a cycle.',
    'IMPORT_DEPENDENCY_INVALID'=>'Unresolved parent source_key.',
    'IMPORT_INVALID_RECORD_TYPE'=>'Unsupported import record_type.',
    'IMPORT_REQUIRED_FIELD'=>'A mandatory import field is empty.',
    'IMPORT_STATUS_REQUIRES_SETSTATE'=>'Use the authorized State transition workflow to change Product status.',
    'IMPORT_REFERENCE_AMBIGUOUS'=>'Specify either source_key or code, not both.',
    'IMPORT_REFERENCE_INVALID'=>'Specify exactly one reference key or code.',
    'IMPORT_REFERENCE_NOT_FOUND'=>'A related Product or unit was not found.',
    'IMPORT_DEPENDENCY_FAILED'=>'The referenced in-file Product was not imported successfully.',
    'IMPORT_TARGET_EXISTS'=>'The target already exists in CREATE_ONLY mode.',
    'IMPORT_TARGET_NOT_FOUND'=>'Target not found for UPDATE_ONLY mode.',
    'IMPORT_EMPTY_UPDATE'=>'The update row contains no changes.',
];
