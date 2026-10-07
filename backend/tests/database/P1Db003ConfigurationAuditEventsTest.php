<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class P1Db003ConfigurationAuditEventsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testConfigurationAuditEventsMigrationHasTheApprovedImmutableSinkShape(): void
    {
        $this->assertTrue($this->db->tableExists('configuration_audit_events'));
        $fields = [];
        foreach ($this->db->getFieldData('configuration_audit_events') as $field) {
            $fields[] = (string) $field->name;
        }
        $this->assertSame([
            'id', 'configuration_type', 'context_type', 'organization_id', 'setting_key',
            'operation', 'old_value_json', 'new_value_json', 'actor_user_id', 'request_id', 'created_at',
        ], $fields);
    }
}
