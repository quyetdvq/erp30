<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class UserDisplayFieldsMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;

    public function testUsersTableHasNullableDisplayFields(): void
    {
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('erp_test', $this->db->database);
        $table = config('Auth')->tables['users'];
        $fields = $this->db->getFieldData($table);
        $metadata = [];
        foreach ($fields as $field) {
            $metadata[$field->name] = $field;
        }
        $this->assertArrayHasKey('full_name', $metadata);
        $this->assertArrayHasKey('avatar_filename', $metadata);
        $this->assertTrue((bool) $metadata['full_name']->nullable);
        $this->assertTrue((bool) $metadata['avatar_filename']->nullable);
    }
}
