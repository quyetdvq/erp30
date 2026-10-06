<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/** Immutable runtime identity contract for one canonical Resource type. */
final readonly class ResourceIdentifierMetadata
{
    public const GLOBAL = 'GLOBAL';
    public const COMPANY = 'COMPANY';
    public const ORGANIZATION = 'ORGANIZATION';

    /** @var list<string> */
    private const SCOPES = [self::GLOBAL, self::COMPANY, self::ORGANIZATION];

    public string $resourceType;
    public string $identifierField;
    public int $identifierMaxLength;
    public string $identifierUniquenessScope;
    public ?string $transportImportAlias;

    public function __construct(
        string $resourceType,
        string $identifierField,
        int $identifierMaxLength,
        string $identifierUniquenessScope,
        ?string $transportImportAlias = null,
    ) {
        $resourceType = strtolower(trim($resourceType));
        $identifierField = strtolower(trim($identifierField));
        $identifierUniquenessScope = strtoupper(trim($identifierUniquenessScope));

        if ($resourceType === '' || preg_match('/^[a-z][a-z0-9_:-]*$/', $resourceType) !== 1) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, ['field' => 'resource_type']);
        }
        if ($identifierField === '' || preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)?$/', $identifierField) !== 1) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, ['field' => 'identifier_field']);
        }
        if ($identifierMaxLength < 1) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, ['field' => 'identifier_max_length']);
        }
        if (! in_array($identifierUniquenessScope, self::SCOPES, true)) {
            throw new DomainException(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, ['field' => 'identifier_uniqueness_scope']);
        }
        if ($transportImportAlias !== null) {
            $transportImportAlias = strtolower(trim($transportImportAlias));
            if ($transportImportAlias === '' || preg_match('/^[a-z][a-z0-9_]*$/', $transportImportAlias) !== 1) {
                throw new DomainException(ResourceIdentifierAdapter::ERROR_METADATA_INVALID, ['field' => 'transport_import_alias']);
            }
        }

        $this->resourceType = $resourceType;
        $this->identifierField = $identifierField;
        $this->identifierMaxLength = $identifierMaxLength;
        $this->identifierUniquenessScope = $identifierUniquenessScope;
        $this->transportImportAlias = $transportImportAlias;
    }

    /** @return array{resource_type:string,identifier_field:string,identifier_max_length:int,identifier_uniqueness_scope:string,transport_import_alias?:string} */
    public function toArray(): array
    {
        $result = [
            'resource_type' => $this->resourceType,
            'identifier_field' => $this->identifierField,
            'identifier_max_length' => $this->identifierMaxLength,
            'identifier_uniqueness_scope' => $this->identifierUniquenessScope,
        ];
        if ($this->transportImportAlias !== null) {
            $result['transport_import_alias'] = $this->transportImportAlias;
        }

        return $result;
    }
}
