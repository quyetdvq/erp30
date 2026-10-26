<?php

declare(strict_types=1);

namespace Tests\M11\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\M11\Support\CanonicalGroupRootFixture;

require_once __DIR__ . '/../Support/CanonicalGroupRootFixture.php';

final class CanonicalGroupRootFixtureTest extends TestCase
{
    public function testExistingRootIsReusedWithoutCreatingOrChangingIt(): void
    {
        $created = 0;
        $root = CanonicalGroupRootFixture::resolve([['id' => '17']], static function () use (&$created): string {
            ++$created;
            return '18';
        });

        self::assertSame('17', $root);
        self::assertSame(0, $created, 'Existing GROUP root must be reused unchanged.');
    }

    public function testAbsentRootIsCreatedExactlyOnceWithinTheCallerTransaction(): void
    {
        $created = 0;
        $root = CanonicalGroupRootFixture::resolve([], static function () use (&$created): string {
            ++$created;
            return '31';
        });

        self::assertSame('31', $root);
        self::assertSame(1, $created);
    }

    public function testMultipleGroupRootsAreRejectedBeforeAnyInsert(): void
    {
        $created = 0;
        try {
            CanonicalGroupRootFixture::resolve([['id' => '1'], ['id' => '2']], static function () use (&$created): string {
                ++$created;
                return '3';
            });
            self::fail('An ambiguous GROUP root was accepted.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('multiple canonical GROUP roots', $e->getMessage());
        }
        self::assertSame(0, $created);
    }

    public function testMalformedExistingRootIdIsRejected(): void
    {
        foreach ([['id' => '0'], ['id' => 'bad'], []] as $row) {
            try {
                CanonicalGroupRootFixture::resolve([$row], static fn (): string => '3');
                self::fail('Invalid canonical GROUP root was accepted.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('invalid canonical GROUP root ID', $e->getMessage());
            }
        }
    }
}
