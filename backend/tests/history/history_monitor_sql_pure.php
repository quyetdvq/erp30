<?php

declare(strict_types=1);

require_once __DIR__ . '/../fixtures/m33_ci_http_exception_stub.php';
require_once __DIR__ . '/../../app/Modules/History/HistorySql.php';

use App\Modules\History\HistorySql as Sql;
use CodeIgniter\HTTP\Exceptions\BadRequestException;

$n=0;
$ok=static function(bool $v,string $why) use (&$n):void {
    if (!$v) throw new RuntimeException('HIS MONITOR SQL: '.$why);
    ++$n;
};
$today=new DateTimeImmutable('2026-10-29T00:00:00Z');
$p=Sql::monitorParams([], $today);
$ok($p['from']==='2026-09-30' && $p['to']==='2026-10-29', 'default 30 UTC days');
$ok($p['page']===1 && $p['page_size']===25, 'default page');
$p=Sql::monitorParams(['page'=>'2','page_size'=>'50','from'=>'2026-09-01','to'=>'2026-09-30',
    'resource_type'=>'customer','resource_id'=>'45','actor_user_id'=>'12','event_type'=>'UPDATE'],$today);
$ok($p['from_db']==='2026-09-01 00:00:00' && $p['to_exclusive_db']==='2026-10-01 00:00:00', 'UTC boundaries');
$plan=Sql::forMonitor($p,'dinktech_');
$ok(str_contains($plan['rows_sql'],'`dinktech_business_history_events`'), 'prefixed history table');
$ok(str_contains($plan['rows_sql'],'`dinktech_users`'), 'prefixed one-page actor JOIN');
$ok(str_contains($plan['rows_sql'],'u.username AS actor_username,u.full_name AS actor_full_name'), 'identity projection');
$ok(str_contains($plan['rows_sql'],'`dinktech_customers`'), 'one-to-one Customer enrichment');
$ok(str_contains($plan['rows_sql'],'COALESCE(c.customer_code,s.supplier_code) AS resource_code')
    && str_contains($plan['rows_sql'],'COALESCE(c.name,s.name) AS resource_name'), 'typed Customer/Supplier resource code/name');
$ok(str_contains($plan['rows_sql'],'`dinktech_suppliers`'), 'one-to-one Supplier enrichment');
$ok(str_contains($plan['rows_sql'],"h.resource_type = 'supplier' AND s.id = h.resource_id"), 'typed, trusted Supplier JOIN');
$ok(str_contains($plan['rows_sql'],"h.resource_type = 'customer' AND c.id = h.resource_id"), 'typed, trusted Customer JOIN');
$ok(!str_contains($plan['count_sql'],'customers'), 'count stays unjoined and stable');
$ok(str_contains($plan['rows_sql'],'h.created_at DESC,h.id DESC LIMIT ? OFFSET ?'), 'bounded indexed sort');
$ok(str_contains($plan['count_sql'],'h.created_at >= ? AND h.created_at < ?'), 'date restriction on count');
foreach(['resource_type','event_type','actor_user_id','resource_id'] as $field) {
    $ok(str_contains($plan['count_sql'],'h.'.$field.' = ?'), 'bound filter '.$field);
}
$ok(substr_count($plan['count_sql'],'?')===count($plan['count_binds']),'count binds complete');
$ok(substr_count($plan['rows_sql'],'?')===count($plan['rows_binds']),'rows binds complete');
$ok(array_slice($plan['rows_binds'],-2)===[50,50],'offset bounds');
$ok(!str_contains($plan['count_sql'],'users'),'user JOIN not in count');
$ok(!str_contains($plan['rows_sql'],'SELECT *'),'no full table projection');
foreach([
    ['resource_type'=>'customer;DROP TABLE'], ['resource_type'=>['customer']],
    ['actor_user_id'=>'1 OR 1=1'],['event_type'=>'OPEN'],['resource_id'=>'2'],
    ['from'=>'2026-01-01'],['to'=>'2026-01-01'],
    ['from'=>'2026-02-30','to'=>'2026-03-01'],
    ['from'=>'2026-01-01','to'=>'2026-06-01'],
    ['from'=>'2026-10-02','to'=>'2026-10-01'],
    ['from'=>'2026-01-01','to'=>'2026-01-01','page_size'=>'51'],
    ['page'=>'1001'], ['sort'=>'-id'], ['q'=>'foo'],
] as $bad) {
    try { Sql::monitorParams($bad,$today); throw new RuntimeException('accepted unsafe filters'); }
    catch(BadRequestException) { $ok(true,'reject unsafe filters'); }
}
foreach(['x`evil','a-b'] as $prefix) {
    try { Sql::forMonitor($p,$prefix); throw new RuntimeException('accepted injection prefix'); }
    catch(InvalidArgumentException) { $ok(true,'reject unsafe DBPrefix'); }
}
try { Sql::forMonitor($p,'ok_','bad;users'); throw new RuntimeException('accepted unsafe users table'); }
catch(InvalidArgumentException) { $ok(true,'reject unsafe Auth table'); }
echo "HIS-02 MONITOR SQL: {$n}/{$n} PASS\n";
