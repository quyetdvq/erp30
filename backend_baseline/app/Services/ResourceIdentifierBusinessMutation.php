<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/**
 * Adapts a Resource use-case mutation to NUM-004 without owning CRUD. The
 * caller-provided mutation remains responsible for the actual business write.
 */
final class ResourceIdentifierBusinessMutation implements AutoNumberingBusinessMutation
{
    public function __construct(
        private readonly ResourceIdentifierAdapter $adapter,
        private readonly NumberingScopePeriodContext $context,
        private readonly AutoNumberingBusinessMutation $business,
    ) {
    }

    public function isIdentifierOccupied(string $identifier): bool
    {
        $this->assertLength($identifier);

        return $this->adapter->isIdentifierOccupied($identifier, $this->context);
    }

    public function persist(string $identifier): void
    {
        $this->assertLength($identifier);
        try {
            $this->business->persist($identifier);
        } catch (\Throwable $exception) {
            if ($exception instanceof AutoNumberingIdentifierCollision
                || $this->adapter->isIdentifierCollision($exception)) {
                throw new AutoNumberingIdentifierCollision($exception);
            }
            throw $exception;
        }
    }

    private function assertLength(string $identifier): void
    {
        if (strlen($identifier) > $this->adapter->metadata()->identifierMaxLength) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_IDENTIFIER_TOO_LONG, [
                'resource_type' => $this->adapter->metadata()->resourceType,
                'max_length' => $this->adapter->metadata()->identifierMaxLength,
            ]);
        }
    }
}
