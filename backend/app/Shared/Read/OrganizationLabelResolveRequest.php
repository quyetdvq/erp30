<?php

declare(strict_types=1);

namespace App\Shared\Read;

use InvalidArgumentException;
use JsonException;
use stdClass;

/**
 * M8.6D frozen POST /organizations/labels/resolve request:
 * {"organization_ids": ["1", "2"]} (NOT a JSON root array).
 * IDs are canonical unsigned BIGINT strings, bounded before hitting SQL/PBAC.
 */
final class OrganizationLabelResolveRequest
{
    /** @return list<string> */
    public static function ids(string $body): array
    {
        // Limit work before decoding potentially untrusted, oversized JSON.
        if (strlen($body) > 8192) {
            throw new InvalidArgumentException('Organization label request exceeds size limit.');
        }
        try {
            $decoded = json_decode($body, false, 5, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid Organization label JSON.', 0, $error);
        }
        // stdClass disambiguates the required object from [] and nested arrays.
        if (! $decoded instanceof stdClass || ! property_exists($decoded, 'organization_ids')) {
            throw new InvalidArgumentException('Missing Organization ID list.');
        }
        $ids = $decoded->organization_ids;
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > 50) {
            throw new InvalidArgumentException('Invalid Organization ID list.');
        }
        $unique = [];
        foreach ($ids as $id) {
            if (! is_int($id) && ! is_string($id)) {
                throw new InvalidArgumentException('Invalid Organization ID.');
            }
            $canonical = PositiveId::value($id);
            // Prefix keys: PHP would otherwise cast <=PHP_INT_MAX numeric IDs to ints.
            $unique['id:' . $canonical] = $canonical;
        }
        return array_values($unique);
    }
}
