<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use App\Exceptions\DomainException;
use App\Modules\Notifications\NotificationRuntimeConfigRules;
use PHPUnit\Framework\TestCase;

final class NotificationRuntimeConfigRulesTest extends TestCase
{
    public function testOnlyNineNonSecretKeysAreManageable(): void
    {
        $keys = array_keys(NotificationRuntimeConfigRules::definitions());
        self::assertCount(9, $keys);
        foreach (['PASSWORD','SECRET','SMTP_FROM','DATABASE','OPERATIONS_WRITE_MODE','ALLOW_SCHEMA_DROP'] as $sensitive) {
            self::assertCount(0, array_filter($keys, static fn (string $key): bool => str_contains($key, $sensitive)));
        }
    }

    public function testChangesAreCanonicalizedAndRevisionIsDecimal(): void
    {
        $payload = ['expected_revision' => '0', 'changes' => [
            ['key' => 'DINKERP_M11_EMAIL_SEND_ENABLED', 'enabled' => false],
            ['key' => 'DINKERP_M11_EMAIL_QUEUE_ENABLED', 'enabled' => true],
        ]];
        $result = NotificationRuntimeConfigRules::changes($payload);
        self::assertSame('0', $result['expected_revision']);
        self::assertSame('DINKERP_M11_EMAIL_QUEUE_ENABLED', $result['changes'][0]['key']);
        self::assertTrue($result['changes'][0]['enabled']);
    }

    /** @dataProvider invalidPayloads */
    public function testUnknownKeysAndInvalidShapesFailClosed(array $payload): void
    {
        $this->expectException(DomainException::class);
        NotificationRuntimeConfigRules::changes($payload);
    }

    /** @return iterable<string,array{0:array<string,mixed>}> */
    public static function invalidPayloads(): iterable
    {
        $good = ['expected_revision' => '0', 'changes' => [
            ['key' => 'DINKERP_M11_EMAIL_SEND_ENABLED', 'enabled' => false],
        ]];
        yield 'secret' => [array_replace($good, ['changes' => [['key' => 'DINKERP_M11_CURSOR_SECRET', 'enabled' => true]]])];
        yield 'unknown' => [array_replace($good, ['changes' => [['key' => 'DINKERP_M11_OPERATIONS_WRITE_MODE', 'enabled' => true]]])];
        yield 'duplicate' => [array_replace($good, ['changes' => [$good['changes'][0], $good['changes'][0]]])];
        yield 'invalid value' => [array_replace($good, ['changes' => [['key' => 'DINKERP_M11_EMAIL_SEND_ENABLED', 'enabled' => 'enabled']]])];
        yield 'extra field' => [array_replace($good, ['force' => true])];
        yield 'bad revision' => [array_replace($good, ['expected_revision' => '01'])];
        yield 'float revision' => [array_replace($good, ['expected_revision' => 0])];
        yield 'empty list' => [array_replace($good, ['changes' => []])];
        yield 'extra switch field' => [array_replace($good, ['changes' => [['key' => 'DINKERP_M11_EMAIL_SEND_ENABLED', 'enabled' => true, 'value' => true]]])];
        yield 'dict not list' => [array_replace($good, ['changes' => ['x' => $good['changes'][0]]])];
    }

    public function testRevisionBoundsDoNotOverflowPhpInteger(): void
    {
        self::assertTrue(NotificationRuntimeConfigRules::revision('0'));
        self::assertTrue(NotificationRuntimeConfigRules::revision((string) PHP_INT_MAX));
        self::assertFalse(NotificationRuntimeConfigRules::revision('9223372036854775808'));
        self::assertFalse(NotificationRuntimeConfigRules::revision('-1'));
    }

    public function testStoredValueOnlyBooleanZeroOrOne(): void
    {
        self::assertTrue(NotificationRuntimeConfigRules::storedValue('0', 'boolean'));
        self::assertTrue(NotificationRuntimeConfigRules::storedValue('1', 'boolean'));
        self::assertFalse(NotificationRuntimeConfigRules::storedValue('enabled', 'boolean'));
        self::assertFalse(NotificationRuntimeConfigRules::storedValue('1', 'string'));
    }
}
