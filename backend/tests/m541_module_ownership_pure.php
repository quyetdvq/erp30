<?php

declare(strict_types=1);

/**
 * M5.4.1 owner-boundary regression. No CI4 boot, composer, HTTP, or database.
 * Pins the active Backend Rewrite module names and the read-only PBAC seam.
 */

use App\Bootstrap\AccessReadAdapters;
use App\Modules\Access\Ports\OrganizationFactsPort;
use App\Modules\Access\Ports\PeopleFactsPort;
use App\Modules\Access\ScopeFactsReader;
use App\Modules\Organization\OrganizationReadRepository;
use App\Modules\People\PeopleReadRepository;

$root = dirname(__DIR__);
$modules = $root . '/app/Modules';
$checked = 0;
$check = static function (bool $condition, string $label) use (&$checked): void {
    if (! $condition) {
        throw new RuntimeException('M5.4.1 module ownership violation: ' . $label);
    }
    ++$checked;
};

$actual = array_values(array_filter(scandir($modules) ?: [], static fn (string $v): bool =>
    $v !== '.' && $v !== '..' && is_dir($modules . '/' . $v)
));
sort($actual);
$expected = ['Access', 'Contact', 'Identity', 'Import', 'Locations', 'Numbering', 'Organization', 'People', 'Pricing', 'Product', 'Settings', 'Workflow'];
$check($actual === $expected, 'runtime modules equal the current Backend Rewrite owner list');
foreach (['OrganizationManagement', 'PeopleManagement', 'EmployeeStructureManagement', 'ReadApi', 'ContactsManagement', 'ProductManagement'] as $obsolete) {
    $check(! file_exists($modules . '/' . $obsolete), 'obsolete root removed: ' . $obsolete);
}
foreach (['Access', 'Organization', 'People', 'Locations', 'Contact', 'Pricing', 'Product', 'Import'] as $owner) {
    $check(is_file($modules . '/' . $owner . '/MODULE.md'), $owner . ' owner MDR exists');
}
foreach ([
    'Organization/Mutation/OrganizationMutationController.php',
    'Organization/Mutation/OrganizationMutationService.php',
    'People/Mutation/PeopleMutationController.php',
    'People/Mutation/PeopleMutationService.php',
    'People/Structure/Mutation/StructureMutationController.php',
    'People/Structure/Mutation/StructureMutationService.php',
] as $relative) {
    $body = (string) file_get_contents($modules . '/' . $relative);
    $owner = explode('/', $relative, 2)[0];
    $check(str_contains($body, 'namespace App\\Modules\\' . $owner . '\\'),
        $relative . ' is under its owning module namespace');
}
$controller = (string) file_get_contents($root . '/app/Controllers/Api/ReadCatalogController.php');
$check(str_contains($controller, 'namespace App\\Controllers\\Api;')
    && str_contains($controller, 'new ReadAccessBridge('),
    'M2 read compatibility controller is outside Modules and retains PBAC');

foreach (['ScopeFactsReader.php', 'RuntimeActorReader.php', 'ReadViewGate.php', 'UnifiedAccessService.php'] as $reader) {
    $source = (string) file_get_contents($modules . '/Access/' . $reader);
    $check(! str_contains($source, 'use App\\Modules\\Organization\\')
        && ! str_contains($source, 'use App\\Modules\\People\\'),
        'Access no longer imports Organization/People implementation: ' . $reader);
    $check(str_contains($source, 'AccessReadAdapters'),
        'Access keeps legacy constructor wiring through the composition adapter: ' . $reader);
}

foreach ([
    $root . '/app/Shared/Read/PositiveId.php',
    $root . '/app/Shared/Read/UtcBusinessDate.php',
    $modules . '/Access/Ports/PeopleFactsPort.php',
    $modules . '/Access/Ports/OrganizationFactsPort.php',
    $modules . '/People/PeopleReadRepository.php',
    $modules . '/Organization/OrganizationReadRepository.php',
    $root . '/app/Bootstrap/AccessReadAdapters.php',
    $modules . '/Access/ScopeFactsReader.php',
] as $file) {
    require_once $file;
}
$check(is_subclass_of(PeopleReadRepository::class, PeopleFactsPort::class),
    'People owner provides the PBAC read-only employee facts port');
$check(is_subclass_of(OrganizationReadRepository::class, OrganizationFactsPort::class),
    'Organization owner provides the PBAC read-only ancestry facts port');
$check(AccessReadAdapters::people() instanceof PeopleFactsPort
    && AccessReadAdapters::organizations() instanceof OrganizationFactsPort,
    'composition root wires both providers without connecting to DB');

$people = new class implements PeopleFactsPort {
    public function employeeById(int|string $employeeId): ?array
    {
        return (string) $employeeId === '3' ? ['id' => '3', 'status' => 'ACTIVE'] : null;
    }
    public function employeeForUser(int|string $userId): ?array
    {
        return (string) $userId === '8' ? ['id' => '3', 'status' => 'ACTIVE'] : null;
    }
    public function effectiveAssignments(int|string $employeeId, string $utcDate, bool $primaryOnly = false): array
    {
        return [['id' => '7', 'employee_id' => (string) $employeeId, 'effective_from' => $utcDate]];
    }
};
$organization = new class implements OrganizationFactsPort {
    public function groupRoots(): array { return [['id' => '1', 'type' => 'GROUP']]; }
    public function ancestorChain(int|string $organizationId): array
    {
        return [['id' => '1'], ['id' => (string) $organizationId]];
    }
};
$scope = new ScopeFactsReader($people, $organization);
$instant = new DateTimeImmutable('2026-10-24T04:15:00Z');
$facts = $scope->employeeFactsAt('8', $instant);
$check($facts['employee']['id'] === '3'
    && $facts['effective_assignments'][0]['employee_id'] === '3'
    && $facts['utc_business_date'] === '2026-10-24',
    'injected ports retain user/assignment/time facts');
$check($scope->employeeFactsAt('9', $instant)['effective_assignments'] === [],
    'missing employee yields no assignments (fail closed)');
$check($scope->organizationAncestorIds('12') === ['1', '12'],
    'injected organization port retains root-first ancestor IDs');

$routes = (string) file_get_contents($root . '/app/Config/Routes.php');
$check(str_contains($routes, 'use App\\Controllers\\Api\\ReadCatalogController;'),
    'existing M2.1 GET routes use the relocated read compatibility controller');
foreach (['M52Routes.php' => 'Organization\\Mutation\\OrganizationMutationController',
          'M53Routes.php' => 'People\\Mutation\\PeopleMutationController',
          'M54Routes.php' => 'People\\Structure\\Mutation\\StructureMutationController'] as $file => $fqcn) {
    $s = (string) file_get_contents($root . '/app/Config/' . $file);
    $check(str_contains($s, $fqcn), $file . ' targets its domain owner');
}

echo 'M5.4.1 MODULE OWNERSHIP: ' . $checked . '/' . $checked . ' assertions PASS.' . PHP_EOL;
