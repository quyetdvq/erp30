<?php

declare(strict_types=1);

namespace App\Modules\Contact\Application;

use App\Exceptions\DomainException;
use App\Infrastructure\Id\PositiveId;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Contact\Application\Ports\ContactRepositoryInterface;
use App\Modules\Contact\Contracts\ContactErrors;
use App\Services\BusinessWorkflowWriteBoundary;
use CodeIgniter\Database\ConnectionInterface;

final class ContactService
{
    public const ERROR_OWNER_TRANSFER_UNSUPPORTED = ContactErrors::ERROR_OWNER_TRANSFER_UNSUPPORTED;
    public const ERROR_STATUS_INVALID = ContactErrors::ERROR_STATUS_INVALID;
    public const ERROR_TYPE_REQUIRED = ContactErrors::ERROR_TYPE_REQUIRED;
    public const ERROR_OWNER_REFERENCE_REQUIRED = ContactErrors::ERROR_OWNER_REFERENCE_REQUIRED;
    public const ERROR_TYPE_UNSUPPORTED = ContactErrors::ERROR_TYPE_UNSUPPORTED;
    public const ERROR_OWNER_NOT_FOUND = ContactErrors::ERROR_OWNER_NOT_FOUND;
    public const ERROR_WARD_PROVINCE_MISMATCH = ContactErrors::ERROR_WARD_PROVINCE_MISMATCH;
    public const ERROR_NOT_FOUND = ContactErrors::ERROR_NOT_FOUND;

    /** @var array<string, string> */
    private const OWNER_TABLES = [
        'EMPLOYEE' => 'employees',
        'ORGANIZATION' => 'organizations',
    ];

    /** @var ConnectionInterface<mixed, mixed> */
    private ConnectionInterface $db;

    /** @param ConnectionInterface<mixed, mixed> $db */
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        ConnectionInterface $db,
        private readonly ?MutationContext $mutationContext = null,
        private readonly ?Clock $clock = null,
    ) {
        $this->db = $db;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $operation = $this->operation();
        $payload = $this->withoutAuditFields($data);
        $type = $this->ownerType($payload);
        $ownerId = $this->ownerId($payload);
        $payload['contact_type'] = $type;
        $payload['contact_ref_id'] = $ownerId;
        $payload = BusinessWorkflowWriteBoundary::create(
            $type === 'ORGANIZATION' ? 'organization_contact' : 'employee_contact', $payload, $this->db,
        );
        $this->assertOwnerExists($type, $ownerId);
        $this->assertWardBelongsToProvince($payload);

        return $this->repo()->insert(array_merge($payload, $operation->createdAudit()));
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (array_key_exists('contact_type', $data) || array_key_exists('contact_ref_id', $data)) {
            throw new DomainException(self::ERROR_OWNER_TRANSFER_UNSUPPORTED);
        }
        $operation = $this->operation();
        $current = $this->repo()->find($id);
        if ($current === null) {
            throw new DomainException(self::ERROR_NOT_FOUND, ['contact_id' => $id]);
        }
        $payload = $this->withoutAuditFields($data);
        BusinessWorkflowWriteBoundary::assertNoDirectStateMutation(
            (string) ($current['contact_type'] ?? '') === 'ORGANIZATION' ? 'organization_contact' : 'employee_contact',
            $current, $payload, $this->db,
        );
        $candidate = array_merge($current, $payload);
        $this->assertWardBelongsToProvince($candidate);

        return $this->repo()->update($id, array_merge($payload, $operation->updatedAudit()));
    }

    public function inactivate(int $id, string $status = 'INACTIVE'): bool
    {
        if (! in_array($status, ['INACTIVE', 'CLOSED'], true)) {
            throw new DomainException(self::ERROR_STATUS_INVALID, ['status' => $status]);
        }

        return $this->update($id, ['status' => $status]);
    }

    /** @return list<array<string, mixed>> */
    public function listForOwner(string $contactType, int $contactRefId, ?string $status = null): array
    {
        $normalizedType = $this->normalizeType($contactType);
        $normalizedOwnerId = PositiveId::normalize($contactRefId, 'contact_ref_id', self::ERROR_OWNER_REFERENCE_REQUIRED);
        $this->assertOwnerExists($normalizedType, $normalizedOwnerId);

        return $this->repo()->findByOwner($normalizedType, $normalizedOwnerId, $status);
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        return $this->repo()->search($filters);
    }

    private function repo(): ContactRepositoryInterface
    {
        return $this->repository;
    }

    /** @param array<string, mixed> $data */
    private function ownerType(array $data): string
    {
        if (! isset($data['contact_type']) || ! is_string($data['contact_type'])) {
            throw new DomainException(self::ERROR_TYPE_REQUIRED, ['field' => 'contact_type']);
        }

        return $this->normalizeType($data['contact_type']);
    }

    /** @param array<string, mixed> $data */
    private function ownerId(array $data): int
    {
        if (! array_key_exists('contact_ref_id', $data)) {
            throw new DomainException(self::ERROR_OWNER_REFERENCE_REQUIRED, ['field' => 'contact_ref_id']);
        }

        return PositiveId::normalize($data['contact_ref_id'], 'contact_ref_id', self::ERROR_OWNER_REFERENCE_REQUIRED);
    }

    private function normalizeType(string $type): string
    {
        $normalized = strtoupper($type);
        if (! array_key_exists($normalized, self::OWNER_TABLES)) {
            throw new DomainException(self::ERROR_TYPE_UNSUPPORTED, ['contact_type' => $type]);
        }

        return $normalized;
    }

    private function assertOwnerExists(string $type, int $ownerId): void
    {
        if (! $this->repo()->ownerExists(self::OWNER_TABLES[$type], $ownerId)) {
            throw new DomainException(self::ERROR_OWNER_NOT_FOUND, [
                'contact_type' => $type,
                'contact_ref_id' => $ownerId,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertWardBelongsToProvince(array $data): void
    {
        if (! array_key_exists('province_id', $data) || ! array_key_exists('ward_id', $data)) {
            return;
        }
        if ($data['province_id'] === null || $data['ward_id'] === null) {
            return;
        }

        $provinceId = PositiveId::normalize($data['province_id'], 'province_id', self::ERROR_WARD_PROVINCE_MISMATCH);
        $wardId = PositiveId::normalize($data['ward_id'], 'ward_id', self::ERROR_WARD_PROVINCE_MISMATCH);
        if (! $this->repo()->wardBelongsToProvince($wardId, $provinceId)) {
            throw new DomainException(self::ERROR_WARD_PROVINCE_MISMATCH, [
                'province_id' => $provinceId,
                'ward_id' => $wardId,
            ]);
        }
    }

    private function operation(): OperationContext
    {
        $context = $this->mutationContext ?? MutationContext::system($this->clock ?? service('clock'));

        return $context->begin();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withoutAuditFields(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['created_at'], $data['updated_at']);

        return $data;
    }
}
