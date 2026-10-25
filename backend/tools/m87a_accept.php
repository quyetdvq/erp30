<?php

declare(strict_types=1);

require_once __DIR__.'/m87/M87ASourceAcceptance.php';
try {
    $accepted=M87ASourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M8.7A SOURCE PASS: '.$accepted['operations'].'/150 routes unchanged; deterministic HTTP preflight, local one-run DEV orchestrator and Product Unicode probe fix.'.PHP_EOL;
    echo 'M8.7A PENDING: actual DEV DB/HTTP execution, fixture coverage, baseline/race and EXPLAIN signoff; production=BLOCKED.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'M8.7A SOURCE FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
}
