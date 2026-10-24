<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root.'/app/Language/en/Contact.php';
$vi = require $root.'/app/Language/vi/Contact.php';
$baseline = require $root.'/../backend_baseline/app/Language/en/Contact.php';
$baselineVi = require $root.'/../backend_baseline/app/Language/vi/Contact.php';
$n=0;
$check=static function(bool $pass, string $name) use (&$n):void {
    if (!$pass) throw new RuntimeException('M5.5 bilingual: '.$name);
    ++$n;
};
$check(is_array($en) && is_array($vi), 'both Contact locales defined');
foreach ($baseline as $code=>$message) {
    $check(($en[$code]??null)===$message, 'baseline en stable: '.$code);
    $check(($vi[$code]??null)===($baselineVi[$code]??null),'baseline vi stable: '.$code);
}
$extraEn = require $root.'/app/Language/en/ApiRuntime.php';
$extraVi = require $root.'/app/Language/vi/ApiRuntime.php';
foreach (['contactPrimaryScopeUnavailable', 'contactStateRequiresSetstate'] as $code) {
    $check(isset($extraEn[$code],$extraVi[$code]) && $extraEn[$code]!==$extraVi[$code], 'extension bilingual: '.$code);
}
$check(array_keys($en)===array_keys($vi), 'both dictionaries contain same stable error codes');
echo 'M5.5 BILINGUAL: '.$n.'/'.$n." PASS\n";
