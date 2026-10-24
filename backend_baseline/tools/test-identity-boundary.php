<?php

declare(strict_types=1);

/** Vendor-free BE-REORG-012 autoload and compatibility boundary smoke check. */
$root = dirname(__DIR__) . '/app/';
spl_autoload_register(static function (string $class) use ($root): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $root . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$pairs = [
    'App\\Services\\ShieldUserIdentityService' => 'App\\Modules\\Identity\\Application\\ShieldUserIdentityService',
    'App\\Services\\UserAvatarService' => 'App\\Modules\\Identity\\Application\\UserAvatarService',
    'App\\Services\\UserAvatarStorage' => 'App\\Modules\\Identity\\Infrastructure\\Storage\\UserAvatarStorage',
    'App\\Services\\UserDisplayName' => 'App\\Modules\\Identity\\Domain\\UserDisplayName',
    'App\\Services\\UserGroupSelection' => 'App\\Modules\\Identity\\Domain\\UserGroupSelection',
    'App\\Repositories\\ShieldSecurityUserRepository' => 'App\\Modules\\Identity\\Infrastructure\\Persistence\\ShieldSecurityUserRepository',
];
foreach ($pairs as $old => $new) {
    if (! class_exists($old) || ! class_exists($new) || ! is_a($old, $new, true)) {
        throw new RuntimeException("Identity compatibility mismatch: {$old}");
    }
    $oldMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($old))->getMethods(ReflectionMethod::IS_PUBLIC));
    $newMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($new))->getMethods(ReflectionMethod::IS_PUBLIC));
    if ($oldMethods !== $newMethods) {
        throw new RuntimeException("Identity public methods drift: {$old}");
    }
}
if (\App\Services\UserDisplayName::normalize('  Nguyễn Văn A ') !== 'Nguyễn Văn A'
    || \App\Services\UserGroupSelection::normalize(['2', '4']) !== ['2', '4']) {
    throw new RuntimeException('Identity validation smoke check failed.');
}
foreach ([
    [static fn (): mixed => \App\Services\UserDisplayName::normalize(' '), 'name'],
    [static fn (): mixed => \App\Services\UserGroupSelection::normalize(['2','2']), 'group_ids'],
] as [$call, $field]) {
    try {
        $call();
        throw new RuntimeException('Expected DomainException.');
    } catch (\App\Exceptions\DomainException $e) {
        if ($e->getErrorCode() !== 'VALIDATION_ERROR' || ($e->getContext()['field'] ?? null) !== $field) {
            throw new RuntimeException("Validation contract drift: {$field}");
        }
    }
}
echo 'IDENTITY BOUNDARY PASS: 6 class aliases, public method parity, input/error smoke checks; no DB.\n';
