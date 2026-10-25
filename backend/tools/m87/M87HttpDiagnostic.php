<?php

declare(strict_types=1);

/** Static allowlisted HTTP failures: no exception body, URL, cookies or tokens. */
final class M87HttpDiagnostic
{
    public static function code(Throwable $error): string
    {
        if ($error instanceof JsonException) return 'HTTP_RESPONSE_NOT_JSON';
        $message=$error->getMessage();
        if (str_contains(strtolower($message), 'case manifest')
            || str_contains(strtolower($message), 'case file')) return 'INVALID_CASE_MANIFEST';
        $map=[
            'DEV account credentials missing'=>'ACTOR_CREDENTIALS_MISSING',
            'DEV CSRF bootstrap failed'=>'CSRF_ENDPOINT_NOT_200',
            'DEV CSRF token missing'=>'CSRF_TOKEN_MISSING',
            'DEV actor authentication failed'=>'LOGIN_HTTP_NOT_200',
            'Authenticated HTTP actor does not match'=>'ACTOR_IDENTITY_MISMATCH',
            'Local HTTP request failed'=>'LOCAL_APACHE_CONNECTION_OR_TIMEOUT',
            'response exceeded 2MB'=>'HTTP_RESPONSE_SIZE_EXCEEDED',
            'Malformed local HTTP status'=>'LOCAL_HTTP_STATUS_INVALID',
            'M8.7 blocks unreviewed HTTP method/path'=>'UNREVIEWED_HTTP_METHOD_OR_PATH',
            'Only reviewed local Apache virtual hosts'=>'INVALID_LOCAL_HTTP_VHOST',
        ];
        foreach ($map as $pattern=>$code) if (str_contains($message,$pattern)) return $code;
        return 'REVIEW_LOCAL_ERROR_LOG';
    }
}
