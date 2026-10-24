<?php
// ONLY a local, vendor-free HTTP transport fixture. Never a real ERP API.
declare(strict_types=1);

session_start();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$locale = str_starts_with($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 'en') ? 'en' : 'vi';
$rid = 'fixture-' . substr(hash('sha256', $path . session_id()), 0, 12);
$token = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
header('Content-Type: application/json; charset=UTF-8');
header('Content-Language: ' . $locale);
header('X-Request-ID: ' . $rid);
header('X-CSRF-TOKEN: ' . $token);
function emitM34Mock(int $status, ?array $body): never {
    http_response_code($status);
    if ($body !== null) { echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    exit;
}
function mockError(int $status, string $code, string $msg): never {
    global $rid;
    emitM34Mock($status, ['error'=>['code'=>$code,'message'=>$msg,'details'=>[]], 'meta'=>['request_id'=>$rid]]);
}
if ($path === '/api/v1/auth/csrf' && $method === 'GET') {
    emitM34Mock(200, ['data'=>['csrf_token'=>$token,'header_name'=>'X-CSRF-TOKEN'], 'meta'=>['request_id'=>$rid]]);
}
if ($path === '/api/v1/auth/me' && $method === 'GET') {
    if (empty($_SESSION['logged_in'])) { mockError(401,'UNAUTHENTICATED','Not authenticated'); }
    emitM34Mock(200, ['data'=>['user'=>['id'=>'4','username'=>'mock-admin']], 'meta'=>['request_id'=>$rid]]);
}
if ($method !== 'GET' && ! hash_equals($token, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    mockError(403,'CSRF_INVALID','CSRF token is invalid');
}
if ($path === '/api/v1/auth/login' && $method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (($input['email'] ?? '') !== 'admin@example.test' || ($input['password'] ?? '') !== 'testpass') {
        mockError(401,'INVALID_CREDENTIALS','Invalid credentials');
    }
    $_SESSION['logged_in'] = true;
    emitM34Mock(200, ['data'=>['user'=>['id'=>'4','username'=>'mock-admin']], 'meta'=>['request_id'=>$rid]]);
}
if (empty($_SESSION['logged_in'])) { mockError(401,'UNAUTHENTICATED','Not authenticated'); }
if ($path === '/api/v1/resources' && $method === 'POST') {
    mockError(422,'VALIDATION_ERROR',$locale === 'vi' ? 'Yeu cau khong hop le.' : 'Invalid request.');
}
mockError(404,'RESOURCE_NOT_FOUND',$locale === 'vi' ? 'Khong tim thay.' : 'Not found.');
