<?php

declare(strict_types=1);

namespace App\Modules\Supplier;

use App\Exceptions\DomainException;
use InvalidArgumentException;

/** Pure, fail-closed write DTO parser. Never send raw HTTP keys to SQL. */
final class SupplierWritePayload
{
    private const MAX_VERSION = 4294967294; // UINT32: reserve space for +1

    /**
     * @param array<string,mixed> $body
     * @return array{profile:array<string,mixed>,all_companies:int,company_ids:list<string>,series:?string,manual:?string}
     */
    public static function create(array $body): array
    {
        self::only($body, [
            'name','supplier_type','supplier_group_code','phone','email','tax_code',
            'address','province_id','ward_id','payment_term_code','supplier_code',
            'numbering_series_code','all_companies','company_ids',
        ]);
        if (!array_key_exists('all_companies', $body) || !array_key_exists('company_ids', $body)) {
            throw new DomainException('SUPPLIER_USAGE_REQUIRED');
        }
        $profile = self::profile(self::without($body, ['numbering_series_code','all_companies','company_ids']), true);
        $manual = $profile['supplier_code'] ?? null;
        unset($profile['supplier_code']);
        $companies = self::usage($body['all_companies'], $body['company_ids']);
        $seriesRaw = $body['numbering_series_code'] ?? null;
        if ($seriesRaw !== null && !is_string($seriesRaw)) {
            throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>'numbering_series_code']);
        }
        $series = SupplierNumberingPolicy::requestedCode($seriesRaw);
        return ['profile'=>$profile,'all_companies'=>$companies['all_companies'],
            'company_ids'=>$companies['company_ids'],'series'=>$series,'manual'=>$manual];
    }

    /** @param array<string,mixed> $body @return array{profile:array<string,mixed>,expected_version:int} */
    public static function patch(array $body): array
    {
        self::only($body, [
            'name','supplier_type','supplier_group_code','phone','email','tax_code',
            'address','province_id','ward_id','payment_term_code','expected_version',
        ]);
        $version = self::version($body);
        $profile = self::profile(self::without($body, ['expected_version']), false);
        return ['profile'=>$profile,'expected_version'=>$version];
    }

    /** @param array<string,mixed> $body @return array{all_companies:int,company_ids:list<string>,expected_version:int} */
    public static function replaceUsage(array $body): array
    {
        self::only($body, ['expected_version','all_companies','company_ids']);
        $version = self::version($body);
        if (!array_key_exists('all_companies', $body) || !array_key_exists('company_ids', $body)) {
            throw new DomainException('SUPPLIER_USAGE_REQUIRED');
        }
        return self::usage($body['all_companies'], $body['company_ids']) + ['expected_version'=>$version];
    }

    /** @param array<string,mixed> $body @return array{status:string,expected_version:int} */
    public static function setState(array $body): array
    {
        self::only($body, ['status','expected_version']);
        $version = self::version($body);
        $target = $body['status'] ?? null;
        if (!is_string($target) || preg_match('/\A[A-Z][A-Z0-9_:-]{0,19}\z/D', $target) !== 1) {
            throw new DomainException('SUPPLIER_STATE_INVALID');
        }
        return ['status'=>$target,'expected_version'=>$version];
    }

    /** @param array<string,mixed> $body */
    private static function version(array $body): int
    {
        $raw = $body['expected_version'] ?? null;
        if (!is_int($raw) || $raw < 1 || $raw > self::MAX_VERSION) {
            throw new DomainException('SUPPLIER_EXPECTED_VERSION_INVALID');
        }
        return $raw;
    }

    /** @return array{all_companies:int,company_ids:list<string>} */
    private static function usage(mixed $all, mixed $companyIds): array
    {
        try { $ids = SupplierRules::validateUsage($all, $companyIds); }
        catch (InvalidArgumentException $e) { throw new DomainException($e->getMessage(), [], $e); }
        return ['all_companies'=>$all ? 1 : 0, 'company_ids'=>$ids];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private static function profile(array $input, bool $create): array
    {
        try { return SupplierRules::validateProfile($input, $create); }
        catch (InvalidArgumentException $e) { throw new DomainException($e->getMessage(), [], $e); }
    }

    /** @param array<string,mixed> $body @param list<string> $fields */
    private static function only(array $body, array $fields): void
    {
        foreach ($body as $key => $_value) {
            if (!is_string($key) || !in_array($key, $fields, true)) {
                throw new DomainException('SUPPLIER_FIELD_UNSUPPORTED');
            }
        }
    }

    /** @param array<string,mixed> $body @param list<string> $excluded @return array<string,mixed> */
    private static function without(array $body, array $excluded): array
    {
        return array_diff_key($body, array_fill_keys($excluded, true));
    }
}
