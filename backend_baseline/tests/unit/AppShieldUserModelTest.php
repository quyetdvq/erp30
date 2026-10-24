<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AppShieldUserModelTest extends CIUnitTestCase
{
    public function testConfiguredProviderAllowsOnlyTheTwoAddedUserFields(): void
    {
        $this->assertSame(\App\Models\UserModel::class, config('Auth')->userProvider);
        $model = model(\App\Models\UserModel::class, false);
        $allowedFields = (new \ReflectionProperty($model, "allowedFields"))->getValue($model);
        $this->assertContains('full_name', $allowedFields);
        $this->assertContains('avatar_filename', $allowedFields);
        $this->assertContains('username', $allowedFields);
        $this->assertNotContains('email', $allowedFields);
        $this->assertNotContains('password', $allowedFields);
    }
}
