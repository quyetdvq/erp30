<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Modules/Access/Query/EffectiveRowActionHints.php';

use App\Modules\Access\Query\EffectiveRowActionHints as H;

$count = 0;
$check = static function (bool $ok, string $message) use (&$count): void {
    if (!$ok) throw new RuntimeException('M10.2 FAIL: ' . $message);
    ++$count;
};
$check(H::actions('product_group') === ['edit','setstate'], 'MASTER action registry');
$check(H::actions('employee_assignment') === ['edit','setstate'], 'Assignment resource, not Employee');
$check(H::actions('organization_contact') === H::actions('employee_contact'), 'Contact resource pair');
$check(H::actions('resource') === ['edit','setstate','delete','configure_numbering'], 'Exact special Resource actions');
try { H::actions('contact'); throw new RuntimeException('Generic contact incorrectly accepted.'); }
catch (InvalidArgumentException) { ++$count; }
try { H::evaluate('product_group', [], static fn (): bool => true); throw new RuntimeException('Empty target accepted.'); }
catch (InvalidArgumentException) { ++$count; }
$seen = [];
$target = ['id'=>'33','status'=>'ACTIVE','organization_id'=>'12'];
$policy = static function (string $action, array $row) use (&$seen): bool {
    $seen[] = [$action,$row['id'],$row['organization_id']];
    return $action === 'edit';
};
$hints = H::evaluate('price_list',$target,$policy);
$check($hints === ['edit'=>true,'setstate'=>false], 'Exact false/true booleans from policy');
$check($seen === [['edit','33','12'],['setstate','33','12']], 'Each decision receives the same trusted row');
$denied = H::evaluate('resource', ['id'=>'1','status'=>'INACTIVE'], static fn (): bool => false);
$check(count($denied) === 4 && !in_array(true,$denied,true), 'Unapproved permissions fail closed');
$check(H::evaluate('employee_contact', ['id'=>'9','status'=>'ACTIVE','organization_id'=>'22'], static fn ($a) => $a==='setstate') === ['edit'=>false,'setstate'=>true], 'Contact owner evaluator remains caller-specific');
echo 'M10.2 EFFECTIVE ROW ACTION HINTS: ' . $count . '/' . $count . " PASS\n";
