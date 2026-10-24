<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use CodeIgniter\Database\ConnectionInterface;

/**
 * Minimal Lifecycle catalog for service tests which exercise business rules.
 *
 * The PHPUnit DatabaseTestTrait can recreate an empty schema before a test;
 * fixture records from another test (or from erp_test before PHPUnit) are not
 * authoritative. Seed them on the SAME connection used by the service.
 *
 * This is test-only data; it never changes an application's production DB.
 */
final class WorkflowLifecycleFixture
{
    private const CODE = 'TEST_BASIC_ORG_STRUCTURE';

    /** @param ConnectionInterface<mixed,mixed> $db @param list<string> $resourceTypes */
    public static function bind(ConnectionInterface $db, array $resourceTypes): void
    {
        if (ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('WORKFLOW_TEST_FIXTURE_REQUIRES_TESTING_ENVIRONMENT');
        }

        $stateIds = [];
        foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'CLOSED'] as $code) {
            $state = $db->table('workflow_states')->where('code', $code)->get()->getRowArray();
            if ($state === null) {
                $db->table('workflow_states')->insert([
                    'code' => $code,
                    'name' => $code,
                    'status' => 'ACTIVE',
                    'created_at' => '2026-10-15 00:00:00',
                ]);
                $stateIds[$code] = (int) $db->insertID();
            } else {
                if (strtoupper((string) ($state['status'] ?? '')) !== 'ACTIVE') {
                    throw new \RuntimeException('WORKFLOW_TEST_STATE_INACTIVE: ' . $code);
                }
                $stateIds[$code] = (int) $state['id'];
            }
        }

        $lifecycle = $db->table('lifecycles')->where('code', self::CODE)->get()->getRowArray();
        if ($lifecycle === null) {
            $db->table('lifecycles')->insert([
                'code' => self::CODE,
                'name' => 'Test BASIC Workflow',
                'status' => 'ACTIVE',
                'created_at' => '2026-10-15 00:00:00',
            ]);
            $lifecycleId = (int) $db->insertID();
        } else {
            if (strtoupper((string) ($lifecycle['status'] ?? '')) !== 'ACTIVE') {
                throw new \RuntimeException('WORKFLOW_TEST_LIFECYCLE_INACTIVE');
            }
            $lifecycleId = (int) $lifecycle['id'];
        }

        foreach (['DRAFT', 'ACTIVE', 'INACTIVE', 'CLOSED'] as $sequence => $code) {
            $member = $db->table('lifecycle_states')
                ->where('lifecycle_id', $lifecycleId)
                ->where('state_id', $stateIds[$code])->get()->getRowArray();
            $expectedInitial = $code === 'DRAFT' ? 1 : 0;
            if ($member === null) {
                $db->table('lifecycle_states')->insert([
                    'lifecycle_id' => $lifecycleId,
                    'state_id' => $stateIds[$code],
                    'sequence' => ($sequence + 1) * 10,
                    'is_initial' => $expectedInitial,
                    'is_final' => $code === 'CLOSED' ? 1 : 0,
                    'created_at' => '2026-10-15 00:00:00',
                ]);
            } elseif ((int) $member['is_initial'] !== $expectedInitial) {
                throw new \RuntimeException('WORKFLOW_TEST_LIFECYCLE_INITIAL_MISMATCH: ' . $code);
            }
        }

        $domains = [
            'organization' => 'ORGANIZATION',
            'department' => 'MASTER',
            'position' => 'MASTER',
            'employee' => 'MASTER',
        ];
        foreach ($resourceTypes as $type) {
            if (! isset($domains[$type])) {
                throw new \InvalidArgumentException('Unsupported fixture resource: ' . $type);
            }
            $resource = $db->table('resources')->where('type', $type)->get()->getRowArray();
            if ($resource === null) {
                $db->table('resources')->insert([
                    'type' => $type,
                    'name' => $type,
                    'domain' => $domains[$type],
                    'status' => 'ACTIVE',
                    'lifecycle_id' => $lifecycleId,
                    'created_at' => '2026-10-15 00:00:00',
                ]);
            } else {
                $db->table('resources')->where('id', (int) $resource['id'])
                    ->update(['lifecycle_id' => $lifecycleId]);
            }
        }
    }
}
