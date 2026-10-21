<?php

declare(strict_types=1);

/**
 * Vendor-free smoke test of BE-REORG-013 continuation (Resource Application).
 * CodeIgniter database stubs here are confined to this standalone PHP process.
 * Never require this file from PHPUnit or production code.
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
    abstract class BaseConnection implements ConnectionInterface {}
}
namespace CodeIgniter\Exceptions {
    class PageNotFoundException extends \RuntimeException {}
}
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}
namespace {
    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }
        $path = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });

    function assertSameResource(mixed $actual, mixed $expected, string $description): void
    {
        if ($actual !== $expected) {
            throw new \RuntimeException($description . ': ' . var_export($actual, true));
        }
    }

    /** @param callable():mixed $call */
    function expectResourceError(callable $call, string $code): void
    {
        try {
            $call();
        } catch (\App\Exceptions\DomainException $e) {
            assertSameResource($e->getErrorCode(), $code, 'Domain error code');
            return;
        }
        throw new \RuntimeException('Expected DomainException: ' . $code);
    }

    final class ResourceMemoryQuery
    {
        /** @var array<string,mixed> */
        private array $equals = [];
        /** @var array<string,list<int>> */
        private array $in = [];
        /** @var list<array{string,string}> */
        private array $orders = [];
        public function __construct(private ResourceMemoryDb $db, private string $table) {}
        public function where(string $name, mixed $value): self { $this->equals[$name] = $value; return $this; }
        public function whereIn(string $name, array $values): self { $this->in[$name] = $values; return $this; }
        public function orderBy(string $name, string $order): self { $this->orders[] = [$name, $order]; return $this; }
        public function select(string $select): self { return $this; }
        /** @return list<array<string,mixed>> */
        private function matched(): array
        {
            $rows = array_values(array_filter($this->db->rows[$this->table] ?? [], function (array $row): bool {
                foreach ($this->equals as $field => $value) {
                    if ((string) ($row[$field] ?? '') !== (string) $value) {
                        return false;
                    }
                }
                foreach ($this->in as $field => $values) {
                    if (! in_array((int) ($row[$field] ?? 0), $values, true)) {
                        return false;
                    }
                }
                return true;
            }));
            $orders = $this->orders;
            usort($rows, static function (array $left, array $right) use ($orders): int {
                foreach ($orders as [$field, $direction]) {
                    $compare = ($left[$field] ?? null) <=> ($right[$field] ?? null);
                    if ($compare !== 0) { return $direction === 'DESC' ? -$compare : $compare; }
                }
                return 0;
            });
            return $rows;
        }
        public function get(): object
        {
            $rows = $this->matched();
            return new class($rows) {
                public function __construct(private array $rows) {}
                public function getRowArray(): ?array { return $this->rows[0] ?? null; }
                public function getResultArray(): array { return $this->rows; }
            };
        }
        public function insert(array $data): bool
        {
            $id = ++$this->db->lastInsertId;
            $this->db->rows[$this->table][] = ['id' => $id, ...$data];
            $this->db->affected = 1;
            return true;
        }
        public function update(array $data): bool
        {
            $this->db->affected = 0;
            foreach ($this->db->rows[$this->table] as &$row) {
                $matched = true;
                foreach ($this->equals as $field => $value) {
                    if ((string) ($row[$field] ?? '') !== (string) $value) { $matched = false; break; }
                }
                if ($matched) { $row = [...$row, ...$data]; ++$this->db->affected; }
            }
            unset($row);
            return true;
        }
        public function delete(): bool
        {
            $old = count($this->db->rows[$this->table]);
            $equals = $this->equals;
            $this->db->rows[$this->table] = array_values(array_filter(
                $this->db->rows[$this->table],
                static function (array $row) use ($equals): bool {
                    foreach ($equals as $field => $value) {
                        if ((string) ($row[$field] ?? '') !== (string) $value) { return true; }
                    }
                    return false;
                },
            ));
            $this->db->affected = $old - count($this->db->rows[$this->table]);
            return true;
        }
    }

    final class ResourceMemoryDb extends \CodeIgniter\Database\BaseConnection
    {
        /** @var array<string,list<array<string,mixed>>> */
        public array $rows = ['resources' => [], 'resource_actions' => []];
        public int $lastInsertId = 0;
        public int $affected = 0;
        public function table(string $name): ResourceMemoryQuery { return new ResourceMemoryQuery($this, $name); }
        public function insertID(): int { return $this->lastInsertId; }
        public function affectedRows(): int { return $this->affected; }
    }

    if (\App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort::ERROR_RESOURCE_LIFECYCLE_INVALID
            !== \App\Services\ResourceWorkflowStateResolver::ERROR_RESOURCE_LIFECYCLE_INVALID
        || \App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort::ERROR_LIFECYCLE_SELF_REFERENCE
            !== \App\Services\ResourceWorkflowStateResolver::ERROR_LIFECYCLE_SELF_REFERENCE
        || ! is_subclass_of(\App\Services\ResourceWorkflowStateResolver::class, \App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort::class)) {
        throw new \RuntimeException('Workflow lifecycle port/legacy resolver mismatch.');
    }

    $db = new ResourceMemoryDb();
    $repo = new \App\Repositories\ResourceCatalogRepository($db);
    $guard = new class implements \App\Modules\Resource\Application\Ports\ResourceLifecycleGuardPort {
        public function assertLifecycleStateCompatibility(string $type, int $lifecycleId): void {}
        public function assertLifecycleReassignmentSafe(string $type, ?int $nextLifecycleId): void {}
        public function allowedPolicyStateCodes(string $type): array { return ['ACTIVE', 'INACTIVE']; }
    };
    $service = new \App\Modules\Resource\Application\ResourceAdministrationService($repo, static fn () => $guard);
    $operation = new \App\Infrastructure\Mutation\OperationContext(
        7,
        new \App\Infrastructure\Time\EvaluationTime(new \DateTimeImmutable('2026-10-21 12:00:00+00:00')),
        'resource-test',
        false,
    );

    expectResourceError(fn () => $service->create(['type' => ''], $operation), 'VALIDATION_ERROR');
    expectResourceError(fn () => $service->create(['type' => 'test', 'domain' => 'COMPANY'], $operation), 'RESOURCE_DOMAIN_INVALID');
    expectResourceError(fn () => $service->create(['type' => 'test', 'domain' => 'MASTER', 'status' => 'INACTIVE'], $operation), 'NATIVE_STATE_REQUIRES_SETSTATE');
    expectResourceError(fn () => $service->create(['type' => 'resource', 'domain' => 'SYSTEM', 'lifecycle_id' => 19], $operation), 'WF_LIFECYCLE_SELF_REFERENCE');
    $id = $service->create(['type' => 'demo_test', 'name' => ' Hello ', 'domain' => 'master'], $operation);
    assertSameResource($repo->findResource($id)['name'], 'hello', 'Create name normalization');
    assertSameResource($repo->findResource($id)['domain'], 'MASTER', 'Create domain normalization');
    assertSameResource($repo->findResource($id)['created_by'], 7, 'Create audit');
    assertSameResource($service->decorate($repo->findResource($id), false)['policy_state_codes'], ['ACTIVE', 'INACTIVE'], 'Policy state projection without action lookup');
    expectResourceError(fn () => $service->update($id, ['domain' => 'SYSTEM'], $operation), 'RESOURCE_DOMAIN_IMMUTABLE');
    expectResourceError(fn () => $service->update($id, ['type' => 'changed'], $operation), 'RESOURCE_TYPE_IMMUTABLE');
    expectResourceError(fn () => $service->update($id, [], $operation), 'VALIDATION_ERROR');
    $service->update($id, ['name' => ' Renamed '], $operation);
    assertSameResource($repo->findResource($id)['name'], 'renamed', 'Update name normalization');
    assertSameResource($repo->findResource($id)['updated_by'], 7, 'Update audit');

    expectResourceError(fn () => $service->assertContactRecoveryAllowed(['type' => 'contact'], ' active '), 'CONTACT_LEGACY_RESOURCE_RETIRED');
    $service->assertContactRecoveryAllowed(['type' => 'demo_test'], 'ACTIVE');
    $service->setState($id, 'INACTIVE', 'ACTIVE', $operation);
    assertSameResource($repo->findResource($id)['status'], 'INACTIVE', 'Native status mutation');
    try {
        $service->setState($id, 'ACTIVE', 'ACTIVE', $operation);
        throw new \RuntimeException('Expected compare-and-set conflict.');
    } catch (\App\Exceptions\ConcurrencyException $e) {
        // Compare-and-set cannot silently overwrite a stale current state.
    }

    expectResourceError(fn () => $service->createAction($id, ['code' => '  '], $operation), 'VALIDATION_ERROR');
    $action = $service->createAction($id, ['code' => ' Edit ', 'description' => 'before'], $operation);
    assertSameResource($action['code'], 'edit', 'Action code normalization');
    assertSameResource($action['created_by'], 7, 'Action audit');
    expectResourceError(fn () => $service->updateAction((int) $action['id'], ['code' => 'destroy'], $action, $operation), 'RESOURCE_ACTION_CODE_IMMUTABLE');
    expectResourceError(fn () => $service->updateAction((int) $action['id'], [], $action, $operation), 'VALIDATION_ERROR');
    $modified = $service->updateAction((int) $action['id'], ['description' => 'after'], $action, $operation);
    assertSameResource($modified['description'], 'after', 'Action update');
    assertSameResource($modified['updated_by'], 7, 'Action updated audit');
    assertSameResource($service->findAction($id + 1, (int) $action['id']), null, 'Action resource ownership');
    assertSameResource(count($service->actions($id)), 1, 'Action listing');

    $query = ['include' => 'actions', 'page' => 2];
    assertSameResource($service->consumeIncludeActions($query), true, 'Include actions accepted');
    assertSameResource($query, ['page' => 2], 'Include stripped before pagination');
    $query = ['include' => 'nope'];
    try {
        $service->consumeIncludeActions($query);
        throw new \RuntimeException('Malformed include unexpectedly accepted.');
    } catch (\CodeIgniter\HTTP\Exceptions\BadRequestException $e) {}
    assertSameResource($service->attachActions([['id' => $id], ['id' => $id + 1]])[0]['actions'][0]['code'], 'edit', 'Page action projection');
    assertSameResource($service->attachActions([['id' => $id + 1]])[0]['actions'], [], 'No actions on unrelated page');

    $service->deleteAction((int) $action['id']);
    assertSameResource($service->actions($id), [], 'Delete action');
    $service->delete($id);
    assertSameResource($repo->findResource($id), null, 'Delete resource');

    // Guard that the cross-domain API façade still authorizes before delegation.
    $source = file_get_contents($app . 'Services/Api030AuthorizationWorkflowService.php');
    foreach (['createResourceAction', 'updateResourceAction', 'deleteResourceAction', 'deleteResourceActionTarget'] as $method) {
        $start = strpos($source, 'public function ' . $method . '(');
        if ($start === false) { throw new \RuntimeException('Missing API-030 method ' . $method); }
        $end = strpos($source, '\n    public function ', $start + 5);
        $body = substr($source, $start, $end === false ? null : $end - $start);
        $authorized = strpos($body, '$this->authorize(');
        $mutation = strpos($body, '$this->resourceService()->createAction(')
            ?: strpos($body, '$this->resourceService()->updateAction(')
            ?: strpos($body, '$this->resourceService()->deleteAction(')
            ?: strpos($body, '$this->resourceService()->delete(');
        if ($authorized === false || $mutation === false || $authorized >= $mutation) {
            throw new \RuntimeException('API-030 authorization/delegation order drift: ' . $method);
        }
    }

    echo "RESOURCE APPLICATION BOUNDARY PASS: catalog CRUD, action CRUD, lifecycle-native exclusions, status CAS, audit, projection and API authorization order; no DB.\n";
}
