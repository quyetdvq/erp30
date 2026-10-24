<?php

declare(strict_types=1);

/**
 * Focused, offline M4 Numbering comparison against the pinned, immutable
 * v1.2.4 canonical parser. Does not bootstrap CI4 or open SQL connections.
 */
require_once __DIR__.'/../app/Exceptions/DomainException.php';
require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingRules.php';
require_once __DIR__.'/../../backend_baseline/app/Services/ParsedNumberingPattern.php';
require_once __DIR__.'/../../backend_baseline/app/Modules/Numbering/Pattern/Application/Ports/NumberingPatternValidatorInterface.php';
require_once __DIR__.'/../../backend_baseline/app/Modules/Numbering/Pattern/Application/Ports/NumberingPatternCompatibilityValidatorInterface.php';
require_once __DIR__.'/../../backend_baseline/app/Modules/Numbering/Pattern/Application/CanonicalNumberingPatternValidator.php';

use App\Exceptions\DomainException;
use App\Modules\Numbering\Configuration\NumberingRules;
use App\Modules\Numbering\Pattern\Application\CanonicalNumberingPatternValidator;

$old = new CanonicalNumberingPatternValidator();
$count = 0;
$check = static function (bool $yes, string $name) use (&$count): void {
    if (!$yes) throw new RuntimeException('M4 differential FAIL: '.$name);
    $count++;
    echo 'PASS: '.$name.PHP_EOL;
};
$series = ['resource_id'=>1, 'status'=>'ACTIVE', 'reset_policy'=>'NEVER', 'scope_type'=>'GROUP'];
$examples = [
    ['INV-{YYYY}-{SEQ:5}', 'YEARLY', 'GROUP'],
    ['SKU-{ORG_CODE}-{SEQ:4}', 'NEVER', 'ORGANIZATION'],
    ['{YYYY}{MM}{DD}-{SEQ:3}', 'DAILY', 'GROUP'],
    ['{SEQ:1}', 'NEVER', 'GROUP'],
    ['{YYYY}-{SEQ:8}', 'YEARLY', 'GROUP'],
    ['INV-{MM}-{SEQ:2}', 'YEARLY', 'GROUP'],
    ['INV-{YYYY}-{SEQ:3}-{SEQ:2}', 'YEARLY', 'GROUP'],
    ['INV-{{SEQ:3}}', 'NEVER', 'GROUP'],
    ['INV-{UNKNOWN}-{SEQ:2}', 'NEVER', 'GROUP'],
    ['INV-{COMPANY_CODE}-{SEQ:2}', 'NEVER', 'GROUP'],
    ['<?php {SEQ:3} ?>', 'NEVER', 'GROUP'],
    ['SELECT customer FROM product-{SEQ:3}', 'NEVER', 'GROUP'],
];
foreach ($examples as [$pattern,$reset,$scope]) {
    $candidate = $series + ['pattern'=>$pattern];
    $candidate['reset_policy'] = $reset;
    $candidate['scope_type'] = $scope;
    $baselineAllowed = true;
    $newAllowed = true;
    try { $old->validate($pattern,$candidate); }
    catch (DomainException $e) {
        if ($e->getErrorCode()!=='NUM_PATTERN_INVALID') throw $e;
        $baselineAllowed = false;
    }
    try { NumberingRules::validatePattern($candidate); }
    catch (DomainException $e) {
        if ($e->getErrorCode()!=='NUM_PATTERN_INVALID') throw $e;
        $newAllowed = false;
    }
    $check($baselineAllowed === $newAllowed, 'Pinned canonical parse parity: '. $pattern);
}
$left = $series + ['pattern'=>'INV-{YYYY}-{SEQ:3}'];
$right = $series + ['pattern'=>'INV-{YYYY}-{SEQ:5}'];
$oldLeft = $old->parse($left['pattern'],$left)->collisionSignature();
$oldRight = $old->parse($right['pattern'],$right)->collisionSignature();
$check($oldLeft === $oldRight, 'Baseline collision ignores SEQ padding width');
$check(NumberingRules::collisionSignature($left) === $oldLeft, 'M4 matches canonical left signature');
$check(NumberingRules::collisionSignature($right) === $oldRight, 'M4 matches canonical right signature');
$check(NumberingRules::collisionSignature($left) === NumberingRules::collisionSignature($right),
    'M4 treats different SEQ widths as colliding');
$check(NumberingRules::collisionSignature(array_replace($left,['status'=>'INACTIVE']))===null,
    'M4 excludes inactive pattern from collision checks');
$code=(string)file_get_contents(__DIR__.'/../app/Modules/Numbering/Configuration/NumberingService.php');
$check(str_contains($code,'NumberingRules::collisionSignature($old)'),
    'M4 Numbering service actually checks canonical signatures');
echo 'TOTAL: '.$count.' M4 baseline pattern differential assertions passed.'.PHP_EOL;
