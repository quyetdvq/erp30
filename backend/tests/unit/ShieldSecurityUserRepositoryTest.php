<?php

declare(strict_types=1);

use App\Repositories\ShieldSecurityUserRepository;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ShieldSecurityUserRepositoryTest extends CIUnitTestCase
{
    public function testUserProjectionMapsShieldNameAndEmailWithoutPasswordHash(): void
    {
        $selected = [];
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('select')->willReturnCallback(function (string $fields) use (&$selected, $builder): BaseBuilder {
            $selected[] = $fields;

            return $builder;
        });
        foreach (['join', 'where', 'orderBy', 'groupBy'] as $method) {
            $builder->method($method)->willReturnSelf();
        }

        $userResult = $this->createMock(ResultInterface::class);
        $userResult->method('getResultArray')->willReturn([[
            'id' => '1',
            'username' => 'quyetdv',
            'name' => 'Đinh Văn Quyết',
            'email' => 'quyetdv@acad.vn',
            'secret2' => '$2y$12$must-not-be-exposed',
            'active' => '1',
            'status' => 'active',
            'created_at' => '2026-10-08 00:00:00',
        ]]);
        $emptyResult = $this->createMock(ResultInterface::class);
        $emptyResult->method('getResultArray')->willReturn([]);
        $builder->method('get')->willReturnOnConsecutiveCalls($userResult, $emptyResult, $emptyResult);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($builder);

        $users = (new ShieldSecurityUserRepository($db))->findAll();

        $this->assertSame('Đinh Văn Quyết', $users[0]['name']);
        $this->assertSame('quyetdv@acad.vn', $users[0]['email']);
        $this->assertArrayNotHasKey('secret2', $users[0]);
        $this->assertSame(
            'u.id, u.username, u.active, u.status, u.created_at, ai.name AS name, ai.secret AS email',
            $selected[0],
        );
    }
}
