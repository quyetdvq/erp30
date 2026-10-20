<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Exceptions\PersistenceException;
use App\Infrastructure\Persistence\FindsRepositoryRow;
use App\Infrastructure\Persistence\ValidatesRepositoryPayload;
use PHPUnit\Framework\TestCase;

/** @internal Canonical implementation contracts: fast, database-free. */
final class CanonicalRepositoryHelperTest extends TestCase
{
    private function driver(): object
    {
        return new class {
            use FindsRepositoryRow;
            use ValidatesRepositoryPayload;

            public const ERROR_PERSISTENCE_FAILED = 'TEST_PERSISTENCE_FAILED';
            public const ERROR_FIELD_UNSUPPORTED = 'TEST_FIELD_UNSUPPORTED';
            public const ERROR_FIELD_REQUIRED = 'TEST_FIELD_REQUIRED';
            private const FIELDS = ['code', 'note'];

            /** @param array<string,mixed> $data
             *  @param list<string> $required
             *  @return array<string,mixed>
             */
            public function validated(array $data, array $required = []): array
            {
                return $this->payload($data, $required);
            }

            /** @return array<string,mixed>|null */
            public function read(callable $query): ?array
            {
                return $this->findRow('find', $query);
            }
        };
    }

    public function testPayloadPreservesAllowedNullAndOriginalKeys(): void
    {
        $data = ['code' => 'A1', 'note' => null];
        self::assertSame($data, $this->driver()->validated($data, ['code', 'note']));
        self::assertSame([], $this->driver()->validated([]));
    }

    public function testPayloadValidatesUnexpectedBeforeMissingFields(): void
    {
        try {
            $this->driver()->validated(['unexpected' => true], ['code']);
            self::fail('Unknown field should fail first.');
        } catch (DomainException $error) {
            self::assertSame('TEST_FIELD_UNSUPPORTED', $error->getErrorCode());
            self::assertSame(['field' => 'unexpected'], $error->getContext());
        }
        try {
            $this->driver()->validated(['note' => null], ['code']);
            self::fail('Missing required field should fail.');
        } catch (DomainException $error) {
            self::assertSame('TEST_FIELD_REQUIRED', $error->getErrorCode());
            self::assertSame(['field' => 'code'], $error->getContext());
        }
    }

    public function testRowReaderPreservesFoundAndMissingSemantics(): void
    {
        $row = new class {
            /** @return array<string,mixed> */
            public function getRowArray(): array { return ['id' => 13]; }
        };
        $empty = new class {
            public function getRowArray(): ?array { return null; }
        };
        self::assertSame(['id' => 13], $this->driver()->read(static fn () => $row));
        self::assertNull($this->driver()->read(static fn () => $empty));
    }

    public function testRowReaderPreservesFalseAndThrownErrorMapping(): void
    {
        try {
            $this->driver()->read(static fn () => false);
            self::fail('False query result must fail.');
        } catch (PersistenceException $error) {
            self::assertSame('TEST_PERSISTENCE_FAILED', $error->getErrorCode());
            self::assertSame(['operation' => 'find'], $error->getContext());
            self::assertNull($error->getPrevious());
        }
        $cause = new RuntimeException('DB disconnected');
        try {
            $this->driver()->read(static function () use ($cause): never { throw $cause; });
            self::fail('Query exception must be wrapped.');
        } catch (PersistenceException $error) {
            self::assertSame('TEST_PERSISTENCE_FAILED', $error->getErrorCode());
            self::assertSame($cause, $error->getPrevious());
        }
    }
}
