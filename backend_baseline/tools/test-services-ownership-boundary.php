<?php

declare(strict_types=1);

/**
 * BE-REORG-014A: standalone, vendor-free, source-only compatibility gate.
 * No database, CodeIgniter bootstrap, HTTP request or mutation is performed.
 * Run from backend: php tools/test-services-ownership-boundary.php
 */
$backend = dirname(__DIR__);
$root = $backend . '/app';
$expected = [
    'AuthorizationGroupService',
    'AutoNumberingConfigurationService',
    'AutoNumberingCounterAllocator',
    'AutoNumberingScopePeriodResolver',
    'BusinessLifecycleTargetAdapter',
    'BusinessLifecycleTransitions',
    'BusinessWorkflowActions',
    'BusinessWorkflowWriteBoundary',
    'CanonicalNumberingPatternValidator',
    'ContactLifecycleAdapter',
    'DisplaySettingsMutationService',
    'FoundationLocationLifecycleAdapter',
    'InitialCreateStateNormalizer',
    'LifecycleService',
    'LifecycleTargetSnapshot',
    'PolicyAdministrationService',
    'ProductLookupService',
    'ResourceIdentifierMetadata',
    'ResourceWorkflowStateResolver',
    'SystemAdminPermissionReconciler',
    'WorkflowBusinessStatus',
];

$map = json_decode((string) file_get_contents($backend . '/tools/service-ownership-map.json'), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($map) || array_keys($map) !== $expected) {
    throw new RuntimeException('Unexpected BE-REORG-014A ownership inventory.');
}

$serviceFiles = glob($root . '/Services/*.php') ?: [];
$shims = array_filter($serviceFiles, static fn (string $file): bool => str_contains((string) file_get_contents($file), 'class_alias('));
if (count($serviceFiles) !== 58 || count($shims) !== 27) {
    throw new RuntimeException('Legacy Services file/shim inventory drift.');
}

spl_autoload_register(static function (string $class) use ($root): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $root . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

foreach ($map as $name => $canonical) {
    $legacy = 'App\\Services\\' . $name;
    $oldPath = $root . '/Services/' . $name . '.php';
    $modulePath = $root . '/' . str_replace('\\', '/', substr($canonical, 4)) . '.php';
    if (! is_file($oldPath) || ! is_file($modulePath)) {
        throw new RuntimeException('Missing legacy/canonical class file: ' . $name);
    }
    $shim = (string) file_get_contents($oldPath);
    if (substr_count($shim, 'class_alias(') !== 1
        || ! str_contains($shim, '\\' . $canonical . '::class')) {
        throw new RuntimeException('Legacy entrypoint does not alias its module owner: ' . $name);
    }
    if (preg_match('/\b(class|interface|trait|enum)\s+' . preg_quote($name, '/') . '\b/', $shim)) {
        throw new RuntimeException('Legacy entrypoint still owns an implementation: ' . $name);
    }
    $source = (string) file_get_contents($modulePath);
    $namespace = substr($canonical, 0, strrpos($canonical, '\\'));
    if (! str_contains($source, 'namespace ' . $namespace . ';')
        || ! preg_match('/\bclass\s+' . preg_quote($name, '/') . '\b/', $source)) {
        throw new RuntimeException('Module implementation/namespace mismatch: ' . $name);
    }
    if (! class_exists($legacy) || ! class_exists($canonical) || ! is_a($legacy, $canonical, true)) {
        throw new RuntimeException('Legacy alias not loadable: ' . $name);
    }
    $oldReflection = new ReflectionClass($legacy);
    $moduleReflection = new ReflectionClass($canonical);
    if ($oldReflection->getName() !== $moduleReflection->getName()
        || realpath((string) $oldReflection->getFileName()) !== realpath($modulePath)
        || $oldReflection->getConstants() !== $moduleReflection->getConstants()
        || array_map(static fn (ReflectionMethod $m): string => $m->getName(), $oldReflection->getMethods())
            !== array_map(static fn (ReflectionMethod $m): string => $m->getName(), $moduleReflection->getMethods())) {
        throw new RuntimeException('Legacy/module contract mismatch: ' . $name);
    }
}

if (! is_subclass_of(
    \App\Services\ResourceWorkflowStateResolver::class,
    \App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort::class,
)) {
    throw new RuntimeException('Resource-Workflow Lifecycle guard contract broken.');
}
if (! is_subclass_of(
    \App\Services\BusinessLifecycleTargetAdapter::class,
    \App\Services\LifecycleTargetAdapter::class,
)) {
    throw new RuntimeException('Legacy Workflow adapter interface broken.');
}
if (! is_subclass_of(
    \App\Services\CanonicalNumberingPatternValidator::class,
    \App\Services\NumberingPatternCompatibilityValidator::class,
)) {
    throw new RuntimeException('Legacy Numbering validator contract broken.');
}
$reflection = new ReflectionMethod(\App\Services\AutoNumberingCounterAllocator::class, 'allocate');
$parameters = array_map(static fn (ReflectionParameter $p): string => (string) $p->getType(), $reflection->getParameters());
if ($parameters !== [
    'array',
    'App\\Services\\NumberingScopePeriodContext',
    'App\\Services\\ParsedNumberingPattern',
    'App\\Services\\AutoNumberingBusinessMutation',
    '?App\\Infrastructure\\Mutation\\OperationContext',
]) {
    throw new RuntimeException('Legacy Numbering allocation signature changed.');
}

$metadata = new \App\Services\ResourceIdentifierMetadata(' PRODUCT ', ' Code ', 32, ' GLOBAL ');
if ($metadata->toArray() !== [
    'resource_type' => 'product', 'identifier_field' => 'code',
    'identifier_max_length' => 32, 'identifier_uniqueness_scope' => 'GLOBAL',
]) {
    throw new RuntimeException('Resource identifier metadata normalization changed.');
}
try {
    new \App\Services\ResourceIdentifierMetadata('bad!', 'id', 1, 'GLOBAL');
    throw new RuntimeException('Invalid identifier metadata accepted.');
} catch (\App\Exceptions\DomainException $error) {
    if ($error->getErrorCode() !== \App\Services\ResourceIdentifierAdapter::ERROR_METADATA_INVALID) {
        throw $error;
    }
}
$snapshot = new \App\Services\LifecycleTargetSnapshot('product', 7, 8, 9, 10);
if ($snapshot->targetId !== 7 || $snapshot->resourceType !== 'product') {
    throw new RuntimeException('Legacy lifecycle snapshot contract changed.');
}

// Frozen BE006 scope: Resource identifiers are Numbering integration, not Resource
// catalogue; certain integration contracts MUST remain outside Modules/Numbering.
foreach ([
    'ResourceIdentifierAdapterRegistry', 'ResourceIdentifierBusinessMutation',
    'NumberingScopePeriodContext', 'ParsedNumberingPattern',
    'AutoNumberingAllocationResult', 'AutoNumberingBusinessMutation',
    'AutoNumberingIdentifierCollision',
] as $excluded) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/Modules/Numbering')) as $path) {
        if ($path->isFile() && $path->getFilename() === $excluded . '.php') {
            throw new RuntimeException('Frozen Numbering boundary violated: ' . $excluded);
        }
    }
}

echo 'SERVICES OWNERSHIP PASS: 21 implementations/DTOs, 21 legacy aliases, preserved Resource-Workflow and Numbering contracts; no DB.' . PHP_EOL;
