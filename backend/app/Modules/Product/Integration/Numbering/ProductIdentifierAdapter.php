<?php

declare(strict_types=1);

namespace App\Modules\Product\Integration\Numbering;

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Services\AutoNumberingIdentifierCollision;
use App\Services\NumberingScopePeriodContext;
use App\Services\ResourceIdentifierAdapter;
use App\Services\ResourceIdentifierMetadata;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;

/** Canonical Product identifier adapter for products.code only. */
final class ProductIdentifierAdapter implements ResourceIdentifierAdapter
{
    public const ERROR_PERSISTENCE_FAILED = 'NUM_PRODUCT_IDENTIFIER_PERSISTENCE_FAILED';

    private readonly ResourceIdentifierMetadata $metadata;

    /** @param ConnectionInterface<mixed, mixed>|null $db */
    public function __construct(private readonly ?ConnectionInterface $db = null)
    {
        $this->metadata = new ResourceIdentifierMetadata(
            resourceType: 'product',
            identifierField: 'products.code',
            identifierMaxLength: 50,
            identifierUniquenessScope: ResourceIdentifierMetadata::GLOBAL,
            transportImportAlias: 'product_code',
        );
    }

    public function metadata(): ResourceIdentifierMetadata
    {
        return $this->metadata;
    }

    public function isIdentifierOccupied(string $identifier, NumberingScopePeriodContext $context): bool
    {
        $connection = $this->connection();
        try {
            $result = $connection->table('products')
                ->select('id')
                ->where('code', $identifier)
                ->limit(1)
                ->get();
        } catch (\Throwable $exception) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'isIdentifierOccupied', $exception);
        }
        if ($result === false) {
            throw new PersistenceException(self::ERROR_PERSISTENCE_FAILED, 'isIdentifierOccupied');
        }

        return $result->getRowArray() !== null;
    }

    public function validateManualIdentifier(string $identifier, NumberingScopePeriodContext $context): void
    {
        if (trim($identifier) === '') {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_IDENTIFIER_INVALID, ['field' => 'product_code']);
        }
        if (strlen($identifier) > $this->metadata->identifierMaxLength) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_IDENTIFIER_TOO_LONG, [
                'resource_type' => $this->metadata->resourceType,
                'max_length' => $this->metadata->identifierMaxLength,
            ]);
        }
        if ($this->isIdentifierOccupied($identifier, $context)) {
            throw new AutoNumberingIdentifierCollision();
        }
    }

    public function isIdentifierCollision(\Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ProductIdentifierUniqueViolation) {
                return true;
            }
            if (! $current instanceof DatabaseException && ! $current instanceof PersistenceException) {
                continue;
            }
            if ((int) $current->getCode() !== 1062) {
                continue;
            }
            if ($current instanceof PersistenceException) {
                $diagnostic = strtolower((string) $current->getDiagnosticMessage());
            } else {
                $diagnostic = strtolower($current->getMessage());
            }
            if (preg_match("/for key [`'](?:products\.)?code[`']/", $diagnostic) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return ConnectionInterface<mixed, mixed> */
    private function connection(): ConnectionInterface
    {
        return $this->db ?? db_connect();
    }
}
