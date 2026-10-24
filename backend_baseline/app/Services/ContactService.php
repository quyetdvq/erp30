<?php

declare(strict_types=1);

namespace App\Services;

use App\Bootstrap\ContactFactory;
use App\Infrastructure\Mutation\MutationContext;
use App\Infrastructure\Time\Clock;
use App\Modules\Contact\Application\ContactService as ModuleContactService;
use App\Modules\Contact\Contracts\ContactErrors;
use App\Repositories\ContactRepository;
use CodeIgniter\Database\ConnectionInterface;

/** Compatibility entrypoint. New Contact behavior belongs in the module. */
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

    private readonly ModuleContactService $delegate;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(
        ?ContactRepository $repository = null,
        ?ConnectionInterface $db = null,
        ?MutationContext $mutationContext = null,
        ?Clock $clock = null,
    ) {
        $this->delegate = ContactFactory::service(
            repository: $repository,
            db: $db,
            mutationContext: $mutationContext,
            clock: $clock,
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->delegate->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        return $this->delegate->update($id, $data);
    }

    public function inactivate(int $id, string $status = 'INACTIVE'): bool
    {
        return $this->delegate->inactivate($id, $status);
    }

    /** @return list<array<string, mixed>> */
    public function listForOwner(string $contactType, int $contactRefId, ?string $status = null): array
    {
        return $this->delegate->listForOwner($contactType, $contactRefId, $status);
    }

    /**
     * @param array<string, scalar|null> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        return $this->delegate->search($filters);
    }
}
