<?php

declare(strict_types=1);

require_once __DIR__ . '/M34BSafety.php';

/**
 * Choose a schema *definition* source, never a writable database.
 *
 * Default: a complete local erp_test schema. If and only if that default is
 * incomplete (missing ERP/Shield tables/columns), check the `erp` schema's
 * information_schema metadata and use SHOW CREATE TABLE from it. No source
 * business data is ever queried and all writes remain confined to a newly
 * generated nonce-named sandbox.
 */
final class M34BTemplateResolver
{
    /**
     * @param callable(string,bool):void $inspect Metadata-only validator.
     * @return array{schema:string,authoritative_metadata_fallback:bool}
     */
    public static function choose(?string $explicitSource, callable $inspect): array
    {
        $isExplicit = $explicitSource !== null && trim($explicitSource) !== '';
        $testSource = M34BSafety::sourceName($isExplicit ? $explicitSource : null);
        try {
            $inspect($testSource, false);
            return ['schema'=>$testSource, 'authoritative_metadata_fallback'=>false];
        } catch (M34BIncompleteTemplate $missing) {
            if ($isExplicit) {
                // Never silently override the caller's requested test source.
                throw $missing;
            }
            try {
                // Exact hard-coded local schema name only; not configurable,
                // and never a mutation or target schema.
                $inspect('erp', true);
            } catch (Throwable $fallbackError) {
                if ($fallbackError instanceof PDOException) {
                    throw $fallbackError;
                }
                throw new M34BFailure(
                    'erp_test schema is incomplete, and the local erp schema-only fallback is not valid: '
                    . $fallbackError->getMessage() . '; no sandbox created.',
                    0,
                    $fallbackError
                );
            }
            return ['schema'=>'erp', 'authoritative_metadata_fallback'=>true];
        }
    }
}
