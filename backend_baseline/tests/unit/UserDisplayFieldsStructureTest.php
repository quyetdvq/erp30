<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

final class UserDisplayFieldsStructureTest extends CIUnitTestCase
{
    public function testConfiguredShieldProviderIsApplicationModel(): void
    {
        self::assertSame(\App\Models\UserModel::class, config('Auth')->userProvider);
        $model = model(\App\Models\UserModel::class);
        $property = new \ReflectionProperty($model, 'allowedFields');
        $fields = $property->getValue($model);
        self::assertContains('full_name', $fields);
        self::assertContains('avatar_filename', $fields);
    }
}
