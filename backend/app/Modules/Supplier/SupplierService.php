<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;

/**
 * Canonical Supplier application service for future T07 API controllers.
 * Read and write adapters retain their separate transaction boundaries.
 * This facade never creates HTTP routes and never exposes bank operations.
 */
final class SupplierService
{
    private readonly SupplierReadService $reader;
    private readonly SupplierWriteService $writer;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $connection = $db ?? db_connect();
        $this->reader = new SupplierReadService($actor, $connection);
        $this->writer = new SupplierWriteService($actor, $connection);
    }

    /** @param array<string,mixed>|null $filters @return array<string,mixed> */
    public function list(?array $filters = null): array { return $this->reader->list($filters); }

    /** @return array<string,mixed> */
    public function show(string $id): array { return $this->reader->show($id); }

    /**
     * Read via the same scoped detail snapshot; project only the three
     * Company Usage fields, never leak the full row to this endpoint.
     * @return array{all_companies:bool,company_ids:list<string>,row_version:string}
     */
    public function companyUsages(string $id): array
    {
        return SupplierApiPolicy::companyUsages($this->reader->show($id));
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input): array { return $this->writer->create($input); }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(string $id, array $input): array { return $this->writer->update($id, $input); }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function replaceCompanyUsage(string $id, array $input): array {
        return $this->writer->replaceCompanyUsage($id, $input);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function setState(string $id, array $input): array { return $this->writer->setState($id, $input); }
}
