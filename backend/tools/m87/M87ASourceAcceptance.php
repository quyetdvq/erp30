<?php

declare(strict_types=1);

require_once __DIR__.'/M87SourceAcceptance.php';

/** Incremental QA-only source acceptance over the frozen M8.7 baseline. */
final class M87ASourceAcceptance
{
    public static function verify(string $root): array
    {
        $base=M87SourceAcceptance::verify($root);
        if ($base['operations']!==150 || $base['resources']!==9) {
            throw new RuntimeException('M8.7A must retain all frozen routes and M8.7 scope probes.');
        }
        $paths=[
            'backend/tools/m87/M87Preflight.php',
            'backend/tools/m87/M87HttpDiagnostic.php',
            'backend/tools/m87_preflight.php',
            'backend/tools/m87_report_summary.php',
            'backend/tools/m87_run_dev.ps1',
            'backend/tests/m87a_preflight_pure.php',
            'backend/tests/m87a_launcher_pure.php',
            'docs/backend-rewrite/M8_7A_DEV_ONE_RUN_DIAGNOSTIC.md',
        ];
        foreach ($paths as $file) if (!is_file($root.'/'.$file) || is_link($root.'/'.$file)) {
            throw new RuntimeException('M8.7A reviewed QA file missing: '.$file);
        }
        $source=(string)file_get_contents($root.'/backend/tools/m87/M87SqlVerifier.php');
        if (!str_contains($source,"'product'=>['product_name','product_child_name','sku','barcode']")) {
            throw new RuntimeException('M8.7A Product Unicode coverage gate missing.');
        }
        return ['operations'=>150,'resources'=>9,'read_only'=>true];
    }
}
