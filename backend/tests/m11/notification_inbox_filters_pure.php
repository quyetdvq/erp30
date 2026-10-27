<?php
require __DIR__ . '/../../app/Exceptions/DomainException.php';
require __DIR__ . '/../../app/Modules/Notifications/NotificationInboxQuery.php';
require __DIR__ . '/../../app/Modules/Notifications/NotificationCursor.php';
use App\Modules\Notifications\NotificationInboxQuery as Query;
use App\Modules\Notifications\NotificationCursor as Cursor;
use App\Exceptions\DomainException;
$checks=0;
$assert = function(bool $ok, string $why) use (&$checks) {if (!$ok) {throw new RuntimeException($why);} $checks++;};
$old = Query::parse(['filter'=>'unread','page_size'=>'20']);
$assert(!$old['extended'], 'Legacy filter must be v1');
$ext = Query::parse(['filter'=>'unread','event_group'=>'workflow','from'=>'2026-10-01','to'=>'2026-10-27']);
$assert($ext['extended'] && $ext['to']==='2026-10-27', 'New query');
$assert(Query::parse(['from'=>'2024-02-29','to'=>'2025-02-28'])['extended'], 'Leap day range');
foreach ([['event_group'=>'finance'],['from'=>'2026-02-30','to'=>'2026-03-01'],['from'=>'2026-10-02'], ['page_size'=>'51'],['from'=>'2026-01-01','to'=>'2027-01-03']] as $bad) {
  try{Query::parse($bad); throw new RuntimeException('Invalid query accepted');}
  catch(DomainException $e){$assert($e->getErrorCode()==='NOTIFICATION_INVALID_REQUEST','Unexpected error');}
}
$key=str_repeat('p',48);
$a=Cursor::encode('3','unread',20,'2026-10-26 12:34:56.123456','10',$key);
$assert(Cursor::decode($a,'3','unread',20,$key)['id']==='10', 'Legacy v1');
$b=Cursor::encodeV2('3','unread',20,'workflow','2026-10-01','2026-10-27','2026-10-26 12:34:56.123456','10',$key);
$assert(Cursor::decodeV2($b,'3','unread',20,'workflow','2026-10-01','2026-10-27',$key)['id']==='10','Filter v2');
foreach ([['3','all',20,'workflow','2026-10-01','2026-10-27',$key],['4','unread',20,'workflow','2026-10-01','2026-10-27',$key],['3','unread',20,'import','2026-10-01','2026-10-27',$key],['3','unread',20,'workflow','2026-10-01','2026-10-26',$key]] as $bad) {
  try{Cursor::decodeV2($b,...$bad);throw new RuntimeException('Wrong-context cursor accepted');}
  catch(DomainException $e){$assert($e->getErrorCode()==='NOTIFICATION_INVALID_CURSOR','Unexpected error');}
}
try {Cursor::decode($b,'3','unread',20,$key);throw new RuntimeException('Wrong-version cursor accepted');}
catch(DomainException $e){$assert($e->getErrorCode()==='NOTIFICATION_INVALID_CURSOR','Unexpected error');}
try {Cursor::decodeV2($a,'3','unread',20,'workflow','2026-10-01','2026-10-27',$key);throw new RuntimeException('Wrong-version cursor accepted');}
catch(DomainException $e){$assert($e->getErrorCode()==='NOTIFICATION_INVALID_CURSOR','Unexpected error');}
echo "NFX-A2 PURE SMOKE: $checks checks PASS\n";
