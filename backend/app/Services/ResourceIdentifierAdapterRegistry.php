<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;
use App\Modules\Product\Integration\Numbering\ProductIdentifierAdapter;
use CodeIgniter\Database\ConnectionInterface;

/** Explicit deterministic registry for approved Resource identifier adapters. */
final class ResourceIdentifierAdapterRegistry
{
    /** @var array<string, ResourceIdentifierAdapter> */
    private array $adapters = [];

    /**
     * @param array<int|string, mixed> $adapters
     * @param ConnectionInterface<mixed, mixed>|null $db
     */
    public function __construct(array $adapters = [], ?ConnectionInterface $db = null)
    {
        if ($adapters === []) {
            $adapters[] = new ProductIdentifierAdapter($db);
        }
        foreach ($adapters as $key => $adapter) {
            if (! $adapter instanceof ResourceIdentifierAdapter) {
                throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, ['key' => (string) $key]);
            }
            $type = $this->type($adapter->metadata()->resourceType);
            if (is_string($key) && trim($key) !== '' && $this->type($key) !== $type) {
                throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource_type' => $type]);
            }
            $this->register($adapter);
        }
        ksort($this->adapters);
    }

    public function register(ResourceIdentifierAdapter $adapter): void
    {
        $metadata = $adapter->metadata();
        $type = $this->type($metadata->resourceType);
        if ($type !== $metadata->resourceType) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource_type' => $metadata->resourceType]);
        }
        if (isset($this->adapters[$type])) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource_type' => $type]);
        }
        $this->adapters[$type] = $adapter;
        ksort($this->adapters);
    }

    public function has(string $resourceType): bool
    {
        return isset($this->adapters[$this->type($resourceType)]);
    }

    public function resolve(string $resourceType): ResourceIdentifierAdapter
    {
        $type = $this->type($resourceType);
        if (! isset($this->adapters[$type])) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_NOT_FOUND, ['resource_type' => $type]);
        }

        return $this->adapters[$type];
    }

    /** @return list<string> */
    public function resourceTypes(): array
    {
        return array_keys($this->adapters);
    }

    private function type(string $resourceType): string
    {
        $type = strtolower(trim($resourceType));
        if ($type === '' || preg_match('/^[a-z][a-z0-9_:-]*$/', $type) !== 1) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_ADAPTER_REGISTRATION_INVALID, ['resource_type' => $resourceType]);
        }

        return $type;
    }
}
