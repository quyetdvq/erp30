<?php

declare(strict_types=1);

namespace App\Modules\Product\Uom;

use App\Authorization\AuthorizationAuditService;
use App\Authorization\AuthorizationService;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;

/** Authorized CRUD and active-reference boundary for the shared Product UOM master. */
final class ProductUomMasterService
{
    public const ERROR_NOT_FOUND = 'PRODUCT_UOM_NOT_FOUND';
    public const ERROR_DUPLICATE_CODE = ProductUomRepository::ERROR_DUPLICATE_CODE;
    public const ERROR_REQUIRED_CODE = 'PRODUCT_UOM_CODE_REQUIRED';
    public const ERROR_CODE_TOO_LONG = 'PRODUCT_UOM_CODE_TOO_LONG';
    public const ERROR_REQUIRED_NAME = 'PRODUCT_UOM_NAME_REQUIRED';
    public const ERROR_NAME_TOO_LONG = 'PRODUCT_UOM_NAME_TOO_LONG';
    public const ERROR_DESCRIPTION_TOO_LONG = 'PRODUCT_UOM_DESCRIPTION_TOO_LONG';
    public const ERROR_INVALID_STATUS = 'PRODUCT_UOM_INVALID_STATUS';
    public const ERROR_INACTIVE_REFERENCE = 'PRODUCT_UOM_INACTIVE_REFERENCE';
    public const ERROR_INVALID_FIELD = 'PRODUCT_UOM_INVALID_FIELD';
    public const ERROR_STATUS_MUTATION_FORBIDDEN = 'PRODUCT_UOM_STATUS_REQUIRES_SETSTATE';
    public const ERROR_AUTHORIZATION_DENIED = 'PRODUCT_UOM_AUTHORIZATION_DENIED';
    public const ERROR_MUTATION_FAILED = 'PRODUCT_UOM_MUTATION_FAILED';
    public const ERROR_CONNECTION_INVALID = 'PRODUCT_UOM_CONNECTION_INVALID';

    /** @var list<string> */
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        private readonly ?ProductUomRepository $repository = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuthorizationAuditService $audit = null,
        ?ConnectionInterface $db = null,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?OperationContext $operation = null): int
    {
        $operation ??= $this->operation();
        $clientHint = $data['evaluation_organization_id'] ?? null;
        $payload = $this->normalizeCreate($data);
        $this->authorize($operation, 'create', null, $payload, $clientHint);

        return $this->repo()->insert($payload, $operation);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'product_uom_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $clientHint = $data['evaluation_organization_id'] ?? null;
        if (array_key_exists('status', $data)) {
            throw new DomainException(self::ERROR_STATUS_MUTATION_FORBIDDEN, ['field' => 'status']);
        }
        $payload = $this->normalizeUpdate($data);
        $this->authorize($operation, 'edit', $current, $payload, $clientHint);

        return $this->repo()->updateMutable($targetId, $payload, $operation);
    }

    public function setState(int $id, string $status, ?OperationContext $operation = null): bool
    {
        $operation ??= $this->operation();
        $targetId = PositiveId::normalize($id, 'product_uom_id', self::ERROR_NOT_FOUND);
        $current = $this->existing($targetId);
        $normalizedStatus = $this->normalizeStatus($status);
        $decision = $this->authorize($operation, 'setstate', $current, []);

        return $this->transaction(function () use ($targetId, $normalizedStatus, $operation, $decision): bool {
            $locked = $this->repo()->findById($targetId);
            if ($locked === null) {
                throw new DomainException(self::ERROR_NOT_FOUND, ['product_uom_id' => $targetId]);
            }
            $updated = $this->repo()->updateStatus($targetId, $normalizedStatus, $operation);
            $this->audit()->recordIfRequired($decision);

            return $updated;
        }, 'product_uom_setstate');
    }

    public function activate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'ACTIVE', $operation);
    }

    public function inactivate(int $id, ?OperationContext $operation = null): bool
    {
        return $this->setState($id, 'INACTIVE', $operation);
    }

    /** @return array<string, mixed>|null */
    public function findById(mixed $id): ?array
    {
        return $this->repo()->findById($id);
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        return $this->repo()->findByCode($code);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->repo()->listAll();
    }

    /** @return array<string, mixed> */
    public function requireActiveProductUom(int $id): array
    {
        $target = $this->existing($id);
        if (($target['status'] ?? null) !== 'ACTIVE') {
            throw new DomainException(self::ERROR_INACTIVE_REFERENCE, ['product_uom_id' => $id]);
        }

        return $target;
    }

    /** @return array<string, mixed> */
    private function existing(int $id): array
    {
        $row = $this->repo()->findById($id);
        if ($row === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['product_uom_id' => $id]);
        }

        return $row;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeCreate(array $data): array
    {
        $this->assertSupportedFields($data);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        $payload['code'] = $this->requiredCode($payload['code'] ?? null);
        $payload['name'] = $this->requiredName($payload['name'] ?? null);
        $payload['description'] = $this->description($payload['description'] ?? null);
        $payload['status'] = array_key_exists('status', $payload)
            ? $this->normalizeStatus($payload['status'])
            : 'ACTIVE';

        return $payload;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function normalizeUpdate(array $data): array
    {
        $this->assertSupportedFields($data);
        $payload = $this->withoutInfrastructureFields($data);
        unset($payload['evaluation_organization_id']);
        if (array_key_exists('code', $payload)) {
            $payload['code'] = $this->requiredCode($payload['code']);
        }
        if (array_key_exists('name', $payload)) {
            $payload['name'] = $this->requiredName($payload['name']);
        }
        if (array_key_exists('description', $payload)) {
            $payload['description'] = $this->description($payload['description']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function assertSupportedFields(array $data): void
    {
        $supported = ['code', 'name', 'description', 'status', 'evaluation_organization_id', 'id', 'created_by', 'updated_by', 'created_at', 'updated_at'];
        foreach ($data as $field => $_value) {
            if (! in_array($field, $supported, true)) {
                throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => $field]);
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function withoutInfrastructureFields(array $data): array
    {
        unset($data['id'], $data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }

    private function requiredCode(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException(self::ERROR_REQUIRED_CODE, ['field' => 'code']);
        }
        $value = trim($value);
        if (mb_strlen($value) > 30) {
            throw new DomainException(self::ERROR_CODE_TOO_LONG, ['field' => 'code', 'max_length' => 30]);
        }

        return $value;
    }

    private function requiredName(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new DomainException(self::ERROR_REQUIRED_NAME, ['field' => 'name']);
        }
        $value = trim($value);
        if (mb_strlen($value) > 100) {
            throw new DomainException(self::ERROR_NAME_TOO_LONG, ['field' => 'name', 'max_length' => 100]);
        }

        return $value;
    }

    private function description(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_FIELD, ['field' => 'description']);
        }
        if (mb_strlen($value) > 255) {
            throw new DomainException(self::ERROR_DESCRIPTION_TOO_LONG, ['field' => 'description', 'max_length' => 255]);
        }

        return $value;
    }

    private function normalizeStatus(mixed $value): string
    {
        if (! is_string($value)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $value]);
        }
        $status = strtoupper(trim($value));
        if (! in_array($status, self::STATUSES, true)) {
            throw new DomainException(self::ERROR_INVALID_STATUS, ['status' => $status]);
        }

        return $status;
    }

    /** @param array<string, mixed>|null $target
     *  @param array<string, mixed> $input
     */
    private function authorize(
        OperationContext $operation,
        string $action,
        ?array $target,
        array $input,
        mixed $clientHint = null,
    ): \App\Authorization\AuthorizationDecision {
        $request = new EvaluationOrganizationRequest('product_uom', $action, $target, $input, $clientHint);
        $decision = $this->auth()->authorize($operation, 'product_uom', $action, $request);
        if (! $decision->isAllowed()) {
            if ($action === 'setstate') {
                $this->audit()->recordIfRequired($decision);
            }
            throw new DomainException(self::ERROR_AUTHORIZATION_DENIED, ['action' => $action]);
        }

        return $decision;
    }

    private function repo(): ProductUomRepository
    {
        return $this->repository ?? new ProductUomRepository($this->db);
    }

    private function auth(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService(db: $this->db);
    }

    private function audit(): AuthorizationAuditService
    {
        return $this->audit ?? new AuthorizationAuditService(db: $this->db);
    }

    private function operation(): OperationContext
    {
        return ($this->mutationContext ?? MutationContext::system($this->clock ?? service('clock')))->begin();
    }

    /** @param callable(): mixed $callback */
    private function transaction(callable $callback, string $operation): mixed
    {
        if (! $this->db instanceof BaseConnection) {
            throw new DomainException(self::ERROR_CONNECTION_INVALID, ['operation' => $operation]);
        }
        $this->db->transBegin();
        try {
            $result = $callback();
            if (! $this->db->transStatus()) {
                throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation]);
            }
            $this->db->transCommit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException(self::ERROR_MUTATION_FAILED, ['operation' => $operation], $exception);
        }
    }
}
