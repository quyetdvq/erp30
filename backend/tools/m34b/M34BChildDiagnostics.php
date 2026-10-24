<?php

declare(strict_types=1);

/**
 * Child CLI diagnostics deliberately expose ONLY fixed reason codes. Never
 * save its raw stdout/stderr: the framework may embed local DB credentials,
 * SQL values or .env content in an exception or stack trace.
 */
final class M34BChildDiagnostics
{
    /** @return array{status:string,code:string,exit_code:int,advice:string} */
    public static function summarize(string $stdout, string $stderr, int $exitCode, bool $passed): array
    {
        if ($passed) {
            return ['status'=>'PASS','code'=>'ATTESTED','exit_code'=>$exitCode,
                'advice'=>'Child CI4 selected and verified its disposable MariaDB sandbox.'];
        }
        $combined = $stdout . "\n" . $stderr;
        $rules = [
            'CHILD_ENVIRONMENT' => '/M34B_CHILD_ENVIRONMENT|environment is not set correctly/i',
            'CHILD_GUARD' => '/M34B_CHILD_GUARD|schema\/nonce guard mismatch/i',
            'DB_GROUP' => '/M34B_CHILD_CONFIG_GROUP/i',
            'DB_SCHEMA' => '/M34B_CHILD_CONFIG_SCHEMA/i',
            'DB_CONFIG' => '/M34B_CHILD_CONFIG|child points to wrong database|defaultGroup/i',
            'DB_PREFIX' => '/M34B_CHILD_PREFIX|child DBPrefix/i',
            'DB_MARKER' => '/M34B_CHILD_MARKER|child database marker failed/i',
            'DB_CONNECTION' => '/SQLSTATE|Unable to connect|connection refused|Access denied for user|MySQLi.+connect/i',
            'CLI_COMMAND' => '/m34b:attest.{0,80}(?:not found|unknown|does not exist)|Command.+not found/i',
            'CLI_BOOT' => '/SUPPORTPATH|Autoload|vendor\/autoload|Fatal error|Parse error|Uncaught|ErrorException/i',
        ];
        $code = 'CLI_EXIT_OR_MARKER';
        foreach ($rules as $candidate => $pattern) {
            if (preg_match($pattern, $combined) === 1) {
                $code = $candidate;
                break;
            }
        }
        $advice = match ($code) {
            'CHILD_ENVIRONMENT' => 'Sandbox child must start as development; the main Backend environment is unchanged.',
            'CHILD_GUARD' => 'Check that CLI child received matching generated schema and nonce.',
            'DB_GROUP' => 'CI4 selected an unexpected default DB group; child remains blocked.',
            'DB_SCHEMA' => 'CI4 resolved a non-sandbox schema/config; child remains blocked.',
            'DB_CONFIG' => 'CI4 resolved a database group different from the disposable sandbox.',
            'DB_PREFIX' => 'CI4 selected a DBPrefix different from the physical sandbox table prefix.',
            'DB_MARKER' => 'CI4 could not read the expected sandbox ownership marker.',
            'DB_CONNECTION' => 'Local PHP MySQLi driver or local MariaDB sandbox connection failed.',
            'CLI_COMMAND' => 'CI4 did not discover the internal m34b:attest command.',
            'CLI_BOOT' => 'CI4 Spark failed before completing the internal attestation command.',
            default => 'Child exited without the exact attestation marker; check installed vendor and PHP CLI.',
        };
        return ['status'=>'BLOCKED','code'=>$code,'exit_code'=>$exitCode,'advice'=>$advice];
    }
}
