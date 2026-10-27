<?php

declare(strict_types=1);

namespace Tests\M11\Integration;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationInboxService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use PHPUnit\Framework\TestCase;
use Tests\M11\Support\M11DatabaseGuard;

require_once __DIR__ . '/../Support/M11DatabaseGuard.php';

/** Guarded erp_test ONLY; all fixture inserts are rolled back. No deliveries are sent. */
final class NotificationInboxFilteringIntegrationTest extends TestCase
{
    private BaseConnection $db;
    private string $userId;
    private string $otherId;
    /** @var array{getenv:string|false,env:mixed,server:mixed} */
    private array $savedSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = M11DatabaseGuard::connect();
        $this->db->transStrict(false);
        if (! $this->db->transBegin()) { throw new \RuntimeException('M11 Inbox fixture transaction failed'); }
        $key = 'DINKERP_M11_CURSOR_SECRET';
        $this->savedSecret = ['getenv' => getenv($key), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null];
        putenv($key . '=' . str_repeat('q', 48));
        $_ENV[$key] = str_repeat('q', 48);
        $_SERVER[$key] = str_repeat('q', 48);
        $this->userId = $this->createUser();
        $this->otherId = $this->createUser();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        $this->db->close();
        $key = 'DINKERP_M11_CURSOR_SECRET';
        $saved = $this->savedSecret;
        if ($saved['getenv'] === false) { putenv($key); } else { putenv($key . '=' . $saved['getenv']); }
        if ($saved['env'] === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $saved['env']; }
        if ($saved['server'] === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $saved['server']; }
        parent::tearDown();
    }

    public function testGroupDateUnreadKeysetAndUserIsolation(): void
    {
        $this->receipt('import.completed', '2026-10-01 00:00:00.000000');
        $a = $this->receipt('workflow.action_required', '2026-10-02 23:59:59.999999');
        $b = $this->receipt('workflow.action_required', '2026-10-02 23:59:59.999999');
        $this->receipt('workflow.action_required', '2026-10-03 00:00:00.000000');
        $this->receipt('workflow.action_required', '2026-10-02 20:00:00.000000', true);
        $this->receipt('workflow.action_required', '2026-10-02 18:00:00.000000', false, $this->otherId);
        $expired = $this->receipt('workflow.action_required', '2026-10-02 17:00:00.000000');
        $this->db->table('user_notifications')->where('id', $expired)
            ->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
        $service = $this->service($this->userId);
        $query = ['filter' => 'unread', 'event_group' => 'workflow', 'from' => '2026-10-01',
            'to' => '2026-10-02', 'page_size' => '1'];
        $first = $service->listing($query);
        self::assertCount(1, $first['items']);
        self::assertSame($b, $first['items'][0]['id']);
        self::assertNotNull($first['next_cursor']);
        $second = $service->listing($query + ['cursor' => $first['next_cursor']]);
        self::assertSame($a, $second['items'][0]['id']);
        self::assertNull($second['next_cursor']);
        self::assertSame('workflow.action_required', $second['items'][0]['type']);
        self::assertNotSame($first['items'][0]['id'], $second['items'][0]['id']);
        $this->assertCursorRejected($service, ['filter' => 'all', 'event_group' => 'workflow',
            'from' => '2026-10-01', 'to' => '2026-10-02', 'page_size' => '1', 'cursor' => $first['next_cursor']]);
        $this->assertCursorRejected($this->service($this->otherId), $query + ['cursor' => $first['next_cursor']]);
        $legacy = $service->listing(['page_size' => '1']);
        self::assertNotNull($legacy['next_cursor']);
        $legacyMore = $service->listing(['page_size' => '1', 'cursor' => $legacy['next_cursor']]);
        self::assertCount(1, $legacyMore['items']);
        $this->assertCursorRejected($service, $query + ['cursor' => $legacy['next_cursor']]);
    }

    /**
     * Actual MariaDB rows must retain pre-C0.3 DTO bytes, cursor, unread and group behavior.
     * Golden output was captured from the original private Inbox projector before extraction.
     */
    public function testAllHistoricalEventDtosRemainIdenticalThroughInboxApi(): void
    {
        $samples = json_decode((string) file_get_contents(
            __DIR__ . '/../Fixtures/notification_inbox_projector_golden.json',
        ), true, 32, JSON_THROW_ON_ERROR);
        $expected = [];
        foreach ($samples as $sample) {
            $row = $sample['row'];
            unset($row['id']);
            $row['recipient_user_id'] = $this->userId;
            $row['event_key'] = 'nfx-c03-' . bin2hex(random_bytes(13));
            $this->db->table('user_notifications')->insert($row);
            $id = (string) $this->db->insertID();
            $dto = $sample['expected'];
            $dto['id'] = $id;
            $expected[$id] = $dto;
        }
        $service = $this->service($this->userId);
        self::assertSame(['unread_count' => 3], $service->summary());
        $page = $service->listing(['page_size' => '20']); // Cursor V1 / legacy mode.
        self::assertNull($page['next_cursor']);
        self::assertCount(count($expected), $page['items']);
        foreach ($page['items'] as $item) {
            self::assertSame($expected[$item['id']], $item);
            self::assertSame(json_encode($expected[$item['id']], JSON_THROW_ON_ERROR),
                json_encode($item, JSON_THROW_ON_ERROR));
        }
        $imports = $service->listing(['event_group' => 'import', 'page_size' => '20']);
        self::assertCount(3, $imports['items']);
        $workflow = $service->listing(['event_group' => 'workflow', 'page_size' => '20']);
        self::assertCount(2, $workflow['items']);
        $pages = [];
        $cursor = null;
        do {
            $query = ['event_group' => 'all', 'page_size' => '2']; // Cursor V2.
            if ($cursor !== null) { $query['cursor'] = $cursor; }
            $next = $service->listing($query);
            array_push($pages, ...array_column($next['items'], 'id'));
            $cursor = $next['next_cursor'];
        } while ($cursor !== null);
        self::assertSame(array_column($page['items'], 'id'), $pages);
    }

    /** @param array<string,mixed> $query */
    private function assertCursorRejected(NotificationInboxService $service, array $query): void
    {
        try { $service->listing($query); self::fail('Wrong-context cursor was accepted'); }
        catch (DomainException $e) { self::assertSame('NOTIFICATION_INVALID_CURSOR', $e->getErrorCode()); }
    }

    private function service(string $userId): NotificationInboxService
    {
        $row = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        if ($row === null) { throw new \RuntimeException('User missing in Inbox test fixture'); }
        return new NotificationInboxService(new User($row), $this->db);
    }

    private function createUser(): string
    {
        $this->db->table('users')->insert([
            'username' => 'nfxa2_' . bin2hex(random_bytes(7)),
            'status' => 'ACTIVE', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return (string) $this->db->insertID();
    }

    private function receipt(string $type, string $createdAt, bool $read = false, ?string $owner = null): string
    {
        $import = str_starts_with($type, 'import.');
        $this->db->table('user_notifications')->insert([
            'recipient_user_id' => $owner ?? $this->userId,
            'event_key' => 'nfx-a2-' . bin2hex(random_bytes(13)),
            'type' => $type,
            'priority' => 'NORMAL',
            'title_key' => $import ? 'notifications.import.completed.title' : 'notifications.workflow.actionRequired.title',
            'body_key' => $import ? 'notifications.import.completed.body' : 'notifications.workflow.actionRequired.body',
            'params_json' => $import ? '{"success_rows":1,"error_rows":0}' : '{"resource_type":"product","state_code":"REVIEW"}',
            'target_type' => $import ? 'import_job' : 'workflow_target',
            'target_id' => '1', 'created_at' => $createdAt,
            'read_at' => $read ? $createdAt : null,
        ]);
        return (string) $this->db->insertID();
    }
}
