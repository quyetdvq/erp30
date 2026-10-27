<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Exceptions/DomainException.php';
require_once __DIR__ . '/../../app/Modules/Access/ResourceStateCatalog.php';
require_once __DIR__ . '/../../app/Modules/Workflow/Execution/BusinessWorkflowRules.php';
require_once __DIR__ . '/../../app/Modules/Notifications/MyWorkflowQuery.php';
require_once __DIR__ . '/../../app/Modules/Notifications/MyWorkflowCursor.php';

use App\Exceptions\DomainException;
use App\Modules\Notifications\MyWorkflowCursor;
use App\Modules\Notifications\MyWorkflowQuery;

$checked = 0;
$assert = static function (bool $good) use (&$checked): void {
    if (! $good) throw new RuntimeException('NFX-B pure smoke failure on check ' . ($checked + 1));
    ++$checked;
};
$assert(MyWorkflowQuery::parse([])['page_size'] === 10);
$assert(MyWorkflowQuery::parse(['resource_type' => 'product', 'page_size' => '1'])['resource_type'] === 'product');
foreach ([['user_id' => '999'], ['resource_type' => 'not_registered'], ['resource_type' => 'product;DROP TABLE users'],
    ['page_size' => '21'], ['page_size' => '0'], ['page_size' => 10], ['resource_type' => null],
    ['page_size' => null], ['cursor' => null], ['cursor' => []], ['cursor' => '']] as $query) {
    try { MyWorkflowQuery::parse($query); throw new RuntimeException('Bad query accepted'); }
    catch (DomainException $e) { $assert($e->getErrorCode() === 'NOTIFICATION_INVALID_REQUEST'); }
}
$secret = str_repeat('a', 40);
$token = MyWorkflowCursor::encode('1', 'product', 10, '2026-10-27 08:00:00.000000', 'product', '123', $secret);
$assert(MyWorkflowCursor::decode($token, '1', 'product', 10, $secret)['resource_id'] === '123');
foreach ([['2', 'product', 10], ['1', 'all', 10], ['1', 'product', 20]] as $context) {
    try { MyWorkflowCursor::decode($token, $context[0], $context[1], $context[2], $secret);
        throw new RuntimeException('Wrong-context cursor accepted'); }
    catch (DomainException $e) { $assert($e->getErrorCode() === 'NOTIFICATION_INVALID_CURSOR'); }
}
try { MyWorkflowCursor::decode('tampered.' . $token, '1', 'product', 10, $secret);
    throw new RuntimeException('Tampered token accepted'); }
catch (DomainException $e) { $assert($e->getErrorCode() === 'NOTIFICATION_INVALID_CURSOR'); }
echo "NFX-B PURE SMOKE: {$checked} checks PASS\n";
