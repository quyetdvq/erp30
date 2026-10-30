<?php

declare(strict_types=1);

// Read-only source audit: no CI4 bootstrap, environment access, SQL or HTTP.
$backend = dirname(__DIR__, 2);
$root = dirname($backend);
$retired = ['DINKERP_' . 'M33B_WRITE_MODE', 'DINKERP_' . 'P2_SUP_API_MODE'];
$paths = ['app', 'tools', 'tests'];
$checked = 0;
foreach ($paths as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backend . '/' . $directory));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (!is_string($contents)) {
            throw new RuntimeException('SUP-MODE: Could not inspect PHP source.');
        }
        foreach ($retired as $mode) {
            if (str_contains($contents, $mode)) {
                throw new RuntimeException('SUP-MODE: Retired configuration remains in ' . $file->getFilename());
            }
        }
        ++$checked;
    }
}
foreach (['src', 'scripts'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/frontend/' . $directory));
    foreach ($files as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['mjs', 'js', 'ts', 'vue'], true)) {
            continue;
        }
        // Source audit may run before SDK generation. Inspect maintained source
        // and the canonical OpenAPI below; the generated SDK has its own gate.
        if ($directory === 'src' && str_starts_with(
            str_replace('\\', '/', $file->getPathname()),
            str_replace('\\', '/', $root . '/frontend/src/api/generated/')
        )) {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (!is_string($contents)) {
            throw new RuntimeException('SUP-MODE: Could not inspect frontend source.');
        }
        foreach ($retired as $mode) {
            if (str_contains($contents, $mode)) {
                throw new RuntimeException('SUP-MODE: Retired configuration remains in ' . $file->getFilename());
            }
        }
        ++$checked;
    }
}
foreach (['contracts/openapi/openapi.yaml', 'README.md', 'backend/app/Database/README.md'] as $contract) {
    $contents = file_get_contents($root . '/' . $contract);
    if (!is_string($contents)) {
        throw new RuntimeException('SUP-MODE: Missing maintained contract ' . $contract);
    }
    foreach ($retired as $mode) {
        if (str_contains($contents, $mode)) {
            throw new RuntimeException('SUP-MODE: Retired configuration remains in ' . $contract);
        }
    }
}
$source = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException('SUP-MODE: Missing source ' . $path);
    }
    return $value;
};
$access = $source('backend/app/Modules/Access/UnifiedAccessService.php');
$supplier = $source('backend/app/Modules/Supplier/SupplierController.php');
$supplierWrite = $source('backend/app/Modules/Supplier/SupplierWriteService.php');
$workflow = $source('backend/app/Modules/Workflow/Execution/BusinessWorkflowService.php');
$notifications = $source('backend/app/Modules/Notifications/SupplierNotificationTarget.php');
$generic = $source('backend/app/Modules/Workflow/Execution/BusinessWorkflowController.php');
foreach ([
    'canonical PBAC mutation decision' => str_contains($access, 'decideMutation('),
    'canonical PBAC transition decision' => str_contains($access, 'decideTransitionAction('),
    'Supplier requires Shield user before service' => strpos($supplier, 'auth()->user()') < strpos($supplier, 'new SupplierService($actor)'),
    'Supplier does not issue its own SQL' => !str_contains($supplier, '->table('),
    'Supplier requests still delegate CAS setstate' => str_contains($supplier, '->setState($id, JsonRequest::readObject($this->request))'),
    'Supplier mutation requires expected_version' => str_contains($supplierWrite, "'expected_version'"),
    'Generic workflow still denies Supplier CAS bypass' => str_contains($workflow, "if (\$type === 'supplier')")
        && str_contains($workflow, "WF_TARGET_ADAPTER_UNAVAILABLE"),
    'Generic workflow still authenticates' => str_contains($generic, "'UNAUTHENTICATED'"),
    'Recipient action still requires Supplier PBAC' => str_contains($notifications, 'decideMutation(self::RESOURCE')
        && str_contains($notifications, 'decideTransitionAction(self::RESOURCE'),
    'Recipient target scoped by persisted GROUP' => str_contains($notifications, "->where('group_organization_id', \$group)"),
] as $name => $pass) {
    if (!$pass) {
        throw new RuntimeException('SUP-MODE: invariant failed: ' . $name);
    }
}
echo 'SUP-TEMP-MODES RETIRED: PASS (' . $checked . ' source files scanned; 10 invariants; no DB/HTTP)' . PHP_EOL;
