<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../app/Modules/Locations/Mutation/LocationTransitionRules.php';

use App\Exceptions\DomainException;
use App\Modules\Locations\Mutation\LocationTransitionRules as Rules;

$checks = 0;
$pass = static function (bool $ok, string $label) use (&$checks): void {
    if (! $ok) { throw new RuntimeException('M5.1 transition: ' . $label); }
    ++$checks;
};
$reject = static function (callable $fn, string $expected) use ($pass): void {
    try { $fn(); }
    catch (DomainException $exception) { $pass($exception->getErrorCode() === $expected, 'Reject ' . $expected); return; }
    throw new RuntimeException('Expected ' . $expected);
};

$definition = ['mode'=>'LIFECYCLE', 'lifecycle_id'=>'88', 'by_code'=>['ACTIVE'=>'11','INACTIVE'=>'12','CLOSED'=>'13']];
$row = ['id'=>'32','status'=>'ACTIVE'];
$transition = ['lifecycle_id'=>88,'from_state_id'=>11,'to_state_id'=>12,'action_code'=>'setstate','status'=>'ACTIVE'];
$pass(Rules::destination($definition, $row, [$transition], 'INACTIVE') === 'INACTIVE', 'Only registered State transition accepted');
$reject(static fn () => Rules::destination($definition, $row, [$transition], 'CLOSED'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [$transition,$transition], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [array_replace($transition, ['status'=>'INACTIVE'])], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [array_replace($transition, ['action_code'=>'force'])], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [array_replace($transition, ['lifecycle_id'=>5])], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [array_replace($transition, ['from_state_id'=>12])], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination($definition, $row, [$transition], 'MISSING'), 'WF_INVALID_TRANSITION');
$reject(static fn () => Rules::destination(array_replace($definition, ['mode'=>'NATIVE']), $row, [$transition], 'INACTIVE'), 'WF_TRANSITION_REQUIRED');
$reject(static fn () => Rules::destination($definition, ['status'=>'UNKNOWN'], [$transition], 'INACTIVE'), 'WF_INVALID_TRANSITION');
$pass(Rules::destination($definition, ['status'=>'INACTIVE'], [array_replace($transition, ['from_state_id'=>12,'to_state_id'=>13])], 'CLOSED')==='CLOSED', 'Transition source from trusted current status');

fwrite(STDOUT, 'M5.1 LOCATION TRANSITIONS: ' . $checks . '/' . $checks . " PASS\n");
