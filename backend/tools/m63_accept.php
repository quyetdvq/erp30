<?php

declare(strict_types=1);

require_once __DIR__.'/m6/M63SourceAcceptance.php';
try {
    $count = M63SourceAcceptance::verify(dirname(__DIR__,2));
    echo 'M6.3A SOURCE PASS: '.$count['prior'].' existing + '.$count['added'].' new = '.$count['total'].'/'.$count['baseline']." routes.\n";
    echo "M6.3A PENDING: authenticated multipart/GD, Apache static serving, SQL metadata/FS rollback and baseline HTTP parity NOT_TESTED.\n";
} catch (Throwable $error) {
    fwrite(STDERR,'M6.3A SOURCE FAIL: '.$error->getMessage()."\n");
    exit(1);
}
