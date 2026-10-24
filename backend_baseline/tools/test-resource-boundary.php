<?php

declare(strict_types=1);

/**
 * Vendor-free BE-REORG-013 boundary smoke test.
 * The lightweight ConnectionInterface shim is confined to this PHP process;
 * it never replaces CodeIgniter's interface in application/PHPUnit runtime.
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface
    {
    }
    abstract class BaseConnection implements ConnectionInterface
    {
    }
}

namespace {
    $root = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($root): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }
        $path = $root . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });

    $aliases = [
        'App\\Repositories\\ResourceCatalogRepository' => 'App\\Modules\\Resource\\Infrastructure\\Persistence\\SqlResourceCatalogRepository',
        'App\\Services\\ResourceNativeStateCatalog' => 'App\\Modules\\Resource\\Domain\\ResourceNativeStateCatalog',
        'App\\Authorization\\ResourceDomain' => 'App\\Modules\\Resource\\Domain\\ResourceDomain',
    ];
    foreach ($aliases as $legacy => $canonical) {
        if (! class_exists($legacy) || ! class_exists($canonical) || ! is_a($legacy, $canonical, true)) {
            throw new \RuntimeException('Resource class alias mismatch: ' . $legacy);
        }
        $old = new \ReflectionClass($legacy);
        $new = new \ReflectionClass($canonical);
        if ($old->getName() !== $new->getName() || $old->getFileName() !== $new->getFileName()) {
            throw new \RuntimeException('Resource aliases are different implementations: ' . $legacy);
        }
        $oldMethods = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $old->getMethods(\ReflectionMethod::IS_PUBLIC));
        $newMethods = array_map(static fn (\ReflectionMethod $method): string => $method->getName(), $new->getMethods(\ReflectionMethod::IS_PUBLIC));
        if ($oldMethods !== $newMethods) {
            throw new \RuntimeException('Resource alias public methods changed: ' . $legacy);
        }
    }

    $domain = \App\Authorization\ResourceDomain::from('MASTER');
    if ($domain !== \App\Modules\Resource\Domain\ResourceDomain::MASTER
        || ! $domain->isGroupScoped()
        || \App\Authorization\ResourceDomain::ORGANIZATION->isGroupScoped()
        || array_map(static fn (\BackedEnum $item): string => $item->value, \App\Authorization\ResourceDomain::cases())
            !== ['SYSTEM', 'MASTER', 'ORGANIZATION']) {
        throw new \RuntimeException('Resource Domain group-scoping contract drift.');
    }
    try {
        \App\Authorization\ResourceDomain::from('COMPANY');
        throw new \RuntimeException('Deprecated Resource Domain unexpectedly accepted.');
    } catch (\ValueError) {
    }

    $native = \App\Services\ResourceNativeStateCatalog::class;
    if (count($native::INDEPENDENT_RESOURCES) !== 10
        || ! $native::isIndependent(' RESOURCE ')
        || ! $native::isIndependent('uSeR')
        || $native::isIndependent('product')
        || $native::codes('authorization_log') !== []
        || $native::codes('resource') !== ['ACTIVE', 'INACTIVE']
        || $native::codes('import_job') !== ['PENDING', 'PROCESSING', 'COMPLETED', 'COMPLETED_WITH_ERRORS', 'FAILED']
        || $native::supportsLifecycleState('resource', 'DRAFT')
        || ! $native::supportsLifecycleState('product', 'DRAFT')) {
        throw new \RuntimeException('Native-state and lifecycle-independent contract drift.');
    }

    /** @internal A bounded one-table SQL read stand-in; no database connection. */
    final class ResourceFakeQuery
    {
        /** @var list<array{string,string}> */
        public array $orders = [];
        /** @var list<array{string,list<int>}> */
        public array $whereIns = [];
        public function __construct(public readonly string $table)
        {
        }
        public function whereIn(string $field, array $values): self
        {
            $this->whereIns[] = [$field, $values];
            return $this;
        }
        public function orderBy(string $field, string $direction): self
        {
            $this->orders[] = [$field, $direction];
            return $this;
        }
        public function get(): object
        {
            return new class {
                /** @return list<array<string,string>> */
                public function getResultArray(): array
                {
                    return [['id' => '31', 'resource_id' => '3', 'code' => 'view']];
                }
            };
        }
    }
    final class ResourceFakeDb implements \CodeIgniter\Database\ConnectionInterface
    {
        /** @var list<ResourceFakeQuery> */
        public array $queries = [];
        public function table(string $name): ResourceFakeQuery
        {
            $query = new ResourceFakeQuery($name);
            $this->queries[] = $query;
            return $query;
        }
    }
    $db = new ResourceFakeDb();
    $catalog = new \App\Repositories\ResourceCatalogRepository($db);
    if ($catalog->findResourceActionsForResources([]) !== [] || $db->queries !== []) {
        throw new \RuntimeException('Empty batch triggered SQL.');
    }
    $result = $catalog->findResourceActionsForResources([3, 7]);
    if ($result !== [['id' => '31', 'resource_id' => '3', 'code' => 'view']]
        || count($db->queries) !== 1
        || $db->queries[0]->table !== 'resource_actions'
        || $db->queries[0]->whereIns !== [['resource_id', [3, 7]]]
        || $db->queries[0]->orders !== [
            ['resource_id', 'ASC'], ['code', 'ASC'], ['id', 'ASC'],
        ]) {
        throw new \RuntimeException('Bulk resource-action SQL shape drift.');
    }
    try {
        $catalog->setResourceStatus(7, 'INVALID', 'ACTIVE', (new \ReflectionClass(\App\Infrastructure\Mutation\OperationContext::class))->newInstanceWithoutConstructor());
        throw new \RuntimeException('Invalid state unexpectedly accepted.');
    } catch (\App\Exceptions\DomainException $exception) {
        if ($exception->getErrorCode() !== 'RESOURCE_STATUS_INVALID') {
            throw $exception;
        }
    }
    echo "RESOURCE BOUNDARY PASS: 3 class aliases (including enum), domain/native-state contracts, SQL batch shape and state validation; no DB.\n";
}
