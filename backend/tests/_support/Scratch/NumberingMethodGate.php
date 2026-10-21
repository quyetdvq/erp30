<?php

declare(strict_types=1);

namespace Tests\Support\Scratch;

use RuntimeException;

/**
 * A deliberately tiny test-only exception to the outer rollback transaction.
 * This class does not grant permission to write: bootstrap and 03C-01 gates
 * must have verified the dedicated scratch target BEFORE this is consulted.
 */
final class NumberingMethodGate
{
    /** @var array<string,string> */
    public const METHODS = [
        'P1AutoNumberingMandatoryRegressionTest' => 'testProductContractGlobalNamespaceGenerationAndAtomicRollback',
        'P1Num005ResourceIdentifierAdapterTest' => 'testFacadeRejectsOversizedGeneratedCandidateBeforeMutation',
        'P1Num005ResourceIdentifierAdapterTest#2' => 'testFacadeDelegatesGeneratedCandidateAndAuthoritativeScopeToAdapter',
        'P1Num006ProductIdentifierAdapterTest' => 'testProductAdapterUsesGlobalOccupancyInsideAutoNumberTransaction',
        'P1Num006ProductIdentifierAdapterTest#2' => 'testRollbackDoesNotPersistProductOrCounter',
    ];

    /** Pure, callable before CI/PHPUnit startup; no DB or file system. */
    public static function assertPair(string $class, string $method): void
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*Test\z/D', $class) !== 1
            || preg_match('/\Atest[A-Z][A-Za-z0-9_]*\z/D', $method) !== 1) {
            throw new RuntimeException('03C-04B STOP: malformed PHPUnit selection.');
        }
        foreach (self::METHODS as $name => $approved) {
            if (explode('#', $name, 2)[0] === $class && $approved === $method) {
                return;
            }
        }
        throw new RuntimeException('03C-04B STOP: method outside the five-method allowlist.');
    }

    public static function assertWorker(string $class, string $method): void
    {
        self::assertPair($class, $method);
        if (! defined('ERP_SCRATCH_NUMBERING_BOOTSTRAPPED') || ERP_SCRATCH_NUMBERING_BOOTSTRAPPED !== true
            || getenv('ERP_SCRATCH_NUMBERING_CLASS') !== $class
            || getenv('ERP_SCRATCH_NUMBERING_METHOD') !== $method
            || NumberingMarker::token() === null) {
            throw new RuntimeException('03C-04B STOP: missing scratch bootstrap or worker ownership token.');
        }
    }
}
