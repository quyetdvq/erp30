<?php

declare(strict_types=1);

/** No HTTP, no SQL and no composer: pin critical M4.2B safety invariants. */
$root=dirname(__DIR__,2);
$get=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$entry=$get('backend/tools/m42b_run.php');
$race=$get('backend/tools/m42/M42Concurrency.php');
$worker=$get('backend/tools/m42/race_worker.php');
$router=$get('backend/tools/m42/router.php');
$ps=$get('backend/tools/test-unit.ps1');
$previous=$get('backend/tools/m42/M42Integration.php');
$assertions=0;
$check=static function (bool $pass,string $why) use (&$assertions):void {
    if (!$pass) throw new RuntimeException('M4.2B offline safety failure: '.$why);
    ++$assertions;
};
$check(str_contains($entry,'M34BSafety::freshName($nonce)')
    && str_contains($entry,'$archive->provision()')
    && str_contains($entry,'$archive->dropOwnSandbox()'), 'fresh nonce-owned DB, cleanup only own DB');
$check(str_contains($entry,'M34BTemplateResolver::choose')
    && str_contains($entry,'M42Fixture::inspectTemplate')
    && str_contains($entry,'$archive->seed()'), 'schema-only metadata and synthetic fixture');
$check(str_contains($entry,"M34BSafety::mysqlEndpoint('127.0.0.1'")
    && str_contains($entry,"'production_write'=>'BLOCKED'"), 'loopback-only and production blocked');
$check(str_contains($entry,"'independent_baseline_live_parity'=>'NOT_TESTED'")
    && str_contains($entry,"'number_issuance_concurrency'=>'NOT_IMPLEMENTED_NOT_TESTED'"),
    'independent baseline and issuance never claimed');
$check(str_contains($entry,'$archive->dropOwnSandbox()')
    && str_contains($entry,"'sandbox_retained_for_review'"), 'fail preserves only nonce-owned SQL sandbox');
$check(str_contains($race,"'M34B_ONLY_SCHEMA'=>\$this->sandbox->name")
    && str_contains($race,"'database.default.database'=>\$this->sandbox->name")
    && str_contains($race,"'database.tests.database'=>\$this->sandbox->name"), 'child DBs pinned to same fresh schema');
$check(str_contains($race,"'CI_ENVIRONMENT'=>'development'")
    && str_contains($race,"'M34B_ONLY_NONCE'")
    && str_contains($race,'M42_RACE_ROLE'), 'child-only schema, nonce and attested role');
$check(str_contains($race,"'session.savePath'=>\$this->dir.'/session'")
    && str_contains($race,'race_a_ready')
    && str_contains($race,'race_b_ready'), 'independent HTTP sessions and synchronized workers');
$check(str_contains($race,'lockDb->beginTransaction()')
    && str_contains($race,'FOR UPDATE')
    && str_contains($race,"'race_a_accepted','race_b_accepted'")
    && str_contains($race,'$this->unlock()'), 'external row-lock barrier and assured release');
$check(str_contains($race,"'race_a_result.json'")
    && str_contains($race,"'race_b_result.json'")
    && str_contains($race,'$statuses!==[201,409]'), 'two HTTP results one winner one conflict');
$check(str_contains($race,"'NUM_SERIES_DUPLICATE'")
    && str_contains($race,'$count!==1 || $defaults!==1 || $counters!==0'),
    'duplicate domain code and persisted series invariants');
$check(str_contains($race,"M42Fixture::changed(\$before,\$after)!==['auto_numbering_series']"),
    'only intended business table changes');
$check(str_contains($worker,'new M42Http($origin)')
    && str_contains($worker,'$client->login($email,$password)')
    && str_contains($worker,'M34Parity::wire'), 'independent authenticated HTTP, normal CSRF and contract');
$check(str_contains($worker,'realpath(dirname($path))!==realpath($dir)')
    && str_contains($worker,'if (!unlink($path))'), 'private config located and immediately erased');
$check(str_contains($worker,"/race_'.\$role.'_result.json'")
    && !str_contains($worker,"'password'=>\$password"), 'only sanitized HTTP results persisted');
$check(str_contains($router,'$allowed = is_string($database)')
    && str_contains($router,"hash_equals(\$nonce, \$marker)")
    && str_contains($router,"/race_' . \$raceRole . '_accepted'"), 'markers written after nonce SQL attestation');
$check(str_contains($router,'$providedRaceReal === $expectedRaceReal')
    && str_contains($router,'!is_link($expectedRaceDir)')
    && str_contains($router,'REQUEST_METHOD'), 'markers restricted to numbered POST and private nonce path');
$check(str_contains($ps,"if (\$suite -eq 'M4-CONCURRENCY')")
    && str_contains($ps,"if (\$suite -eq 'M4-INTEGRATION')")
    && str_contains($ps,"'tools/m42b_run.php'"), 'opt-in PS suite never broadens original M4 test');
$check(str_contains($previous,"'two_writer_concurrency'=>'NOT_TESTED'")
    && str_contains($previous,"'independent_baseline_live_parity'=>'NOT_TESTED'"),
    'previous M4.2A.1 result never retroactively claims concurrency');
$check(!str_contains($entry,'DROP DATABASE ') && !str_contains($worker,'DROP DATABASE ')
    && !str_contains($race,'DROP DATABASE '), 'no unrestricted raw database destruction');
echo 'PASS: '.$assertions.' M4.2B nonce isolation, two-writer coordination, SQL and false-claim gates.'.PHP_EOL;
