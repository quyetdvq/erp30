<?php

declare(strict_types=1);

use App\Authorization\AuthorizationDecision;
use App\Authorization\EvaluationOrganizationRequest;
use App\Exceptions\DomainException;
use App\Infrastructure\Mutation\OperationContext;
use App\Infrastructure\Time\EvaluationTime;
use App\Services\AutoNumberingConfigurationService;
use App\Services\CanonicalNumberingPatternValidator;
use App\Services\NumberingAuthorizationPort;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Performance\TransactionalDatabaseTestTrait;

/** @internal */
final class P1Num002NumberingPatternValidatorTest extends CIUnitTestCase
{
    use TransactionalDatabaseTestTrait;

    protected $namespace = null;

    public function testAllCanonicalTokensParseIntoDeterministicMetadata(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        $pattern = 'INV-{YYYY}-{YY}-{MM}-{DD}-{COMPANY_CODE}-{ORG_CODE}-{RESOURCE}-{SEQ:6}';
        $parsed = $validator->parse($pattern, $this->series('COMPANY', 'NEVER'));

        $this->assertSame($pattern, $parsed->normalizedPattern);
        $this->assertSame(6, $parsed->sequenceWidth);
        $this->assertSame(['YYYY', 'YY', 'MM', 'DD', 'COMPANY_CODE', 'ORG_CODE', 'RESOURCE', 'SEQ'], $parsed->tokens);
        $this->assertSame(['YYYY', 'YY', 'MM', 'DD'], $parsed->dateTokens);
        $this->assertTrue($parsed->hasDateDiscriminator);
        $this->assertTrue($parsed->hasCompanyDiscriminator);
        $this->assertTrue($parsed->hasOrganizationDiscriminator);
        $this->assertSame($parsed->toArray(), $validator->parse($pattern, $this->series('COMPANY', 'NEVER'))->toArray());
    }

    public function testLiteralOnlyAndMultipleSequencePatternsAreRejected(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        foreach (['literal', '{SEQ:4}-{SEQ:5}'] as $pattern) {
            $this->assertInvalid($validator, $pattern);
        }
        $this->assertSame(4, $validator->parse('X{SEQ:4}', $this->series('GROUP', 'NEVER'))->sequenceWidth);
    }

    public function testMalformedSequenceWidthsAreRejectedWithoutNormalization(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        foreach (['{SEQ}', '{SEQ:0}', '{SEQ:-1}', '{SEQ:x}', '{SEQ:1.5}', '{SEQ: 6}', '{seq:6}', '{SEQ:6:2}'] as $pattern) {
            $this->assertInvalid($validator, $pattern);
        }
    }

    public function testUnknownUnbalancedAndExecutableLookingExpressionsAreRejected(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        foreach (['{UNKNOWN}', '{phpinfo()}', '${...}', '{{...}}', '{SQL:SELECT 1}', 'X{SEQ:4'] as $pattern) {
            $this->assertInvalid($validator, $pattern);
        }
        $this->assertSame('<template syntax> {SEQ:4}', $validator->parse('<template syntax> {SEQ:4}')->normalizedPattern);
    }

    public function testResetPolicyRequiresOnlyItsMinimumPeriodDiscriminator(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        $this->assertNotNull($validator->parse('{SEQ:4}', $this->series('GROUP', 'NEVER')));
        $this->assertInvalid($validator, '{SEQ:4}', $this->series('GROUP', 'YEARLY'));
        $this->assertNotNull($validator->parse('{YYYY}{SEQ:4}', $this->series('GROUP', 'YEARLY')));
        $this->assertInvalid($validator, '{YYYY}{SEQ:4}', $this->series('GROUP', 'MONTHLY'));
        $this->assertNotNull($validator->parse('{YY}{MM}{SEQ:4}', $this->series('GROUP', 'MONTHLY')));
        $this->assertInvalid($validator, '{YYYY}{MM}{SEQ:4}', $this->series('GROUP', 'DAILY'));
        $this->assertNotNull($validator->parse('{YYYY}{MM}{DD}{SEQ:4}', $this->series('GROUP', 'DAILY')));
        $this->assertNotNull($validator->parse('{YYYY}{MM}{DD}{SEQ:4}', $this->series('GROUP', 'NEVER')));
    }

    public function testNamespaceCompatibilityUsesExactCanonicalDiscriminators(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        $this->assertInvalid($validator, '{SEQ:4}', $this->series('COMPANY', 'NEVER', 'GLOBAL'));
        $this->assertNotNull($validator->parse('{COMPANY_CODE}{SEQ:4}', $this->series('COMPANY', 'NEVER', 'GLOBAL')));
        $this->assertInvalid($validator, '{SEQ:4}', $this->series('ORGANIZATION', 'NEVER', 'GLOBAL'));
        $this->assertNotNull($validator->parse('{ORG_CODE}{SEQ:4}', $this->series('ORGANIZATION', 'NEVER', 'GLOBAL')));
        $this->assertInvalid($validator, '{SEQ:4}', $this->series('ORGANIZATION', 'NEVER', 'COMPANY'));
        $this->assertNotNull($validator->parse('{ORG_CODE}{SEQ:4}', $this->series('ORGANIZATION', 'NEVER', 'COMPANY')));
        foreach ([
            $this->series('GROUP', 'NEVER', 'GLOBAL'),
            $this->series('COMPANY', 'NEVER', 'COMPANY'),
            $this->series('ORGANIZATION', 'NEVER', 'ORGANIZATION'),
        ] as $series) {
            $this->assertNotNull($validator->parse('{SEQ:4}', $series));
        }
    }

    public function testContextTokensAndStaticLengthProofAreBounded(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        $this->assertInvalid($validator, '{COMPANY_CODE}{SEQ:4}', $this->series('GROUP', 'NEVER'));
        $this->assertNotNull($validator->parse('{COMPANY_CODE}{SEQ:4}', $this->series('COMPANY', 'NEVER')));
        $org = $validator->parse('{ORG_CODE}{SEQ:4}', $this->series('ORGANIZATION', 'NEVER'));
        $this->assertTrue($org->hasOrganizationDiscriminator);
        $this->assertNotNull($validator->parse('ABC{SEQ:4}', ['identifier_max_length' => 10]));
        $this->assertInvalid($validator, 'ABCDEFG{SEQ:4}', ['identifier_max_length' => 10]);
    }

    public function testCollisionComparisonRejectsEquivalentStreamsNotSequenceWidthDifferences(): void
    {
        $validator = new CanonicalNumberingPatternValidator();
        $base = $this->series('COMPANY', 'NEVER', 'GLOBAL');
        $this->assertInvalidCollision($validator, [
            array_merge($base, ['pattern' => 'INV-{COMPANY_CODE}-{SEQ:4}']),
            array_merge($base, ['pattern' => 'INV-{COMPANY_CODE}-{SEQ:6}']),
        ]);
        $validator->validateSeriesSet([
            array_merge($base, ['pattern' => 'INV-{COMPANY_CODE}-{SEQ:4}']),
            array_merge($base, ['pattern' => 'ORD-{COMPANY_CODE}-{SEQ:4}']),
        ]);
        $this->assertInvalidCollision($validator, [
            array_merge($base, ['pattern' => 'INV-{COMPANY_CODE}-{SEQ:4}']),
            array_merge($base, ['pattern' => 'INV-{COMPANY_CODE}-{SEQ:5}']),
        ]);
    }

    public function testNum001BoundaryUsesRealValidatorBeforePersistenceAndConsumesNoCounter(): void
    {
        $resourceId = $this->resource('num002-integration');
        $service = new AutoNumberingConfigurationService(
            authorization: new Num002AllowAuthorization(),
            patternValidator: new CanonicalNumberingPatternValidator(),
            db: $this->db,
        );
        $before = (int) $this->db->table('auto_numbering_counters')->countAllResults();
        $payload = [
            'resource_id' => $resourceId,
            'series_code' => 'INVALID',
            'name' => 'NUM-002 invalid',
            'scope_type' => 'GROUP',
            'pattern' => '{SEQ}',
            'reset_policy' => 'NEVER',
            'generation_mode' => 'AUTO_REQUIRED',
            'start_number' => 1,
            'increment_by' => 1,
            'is_default' => false,
            'status' => 'ACTIVE',
        ];
        try {
            $service->create($payload, $this->operation());
            $this->fail('Expected canonical pattern rejection.');
        } catch (DomainException $exception) {
            $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
        }
        $this->assertSame($before, (int) $this->db->table('auto_numbering_counters')->countAllResults());
        $id = $service->create(array_merge($payload, [
            'resource_id' => $resourceId,
            'series_code' => 'VALID',
            'status' => 'ACTIVE',
            'pattern' => 'N-{SEQ:4}',
        ]), $this->operation());
        $this->assertGreaterThan(0, $id);
        $this->assertSame($before, (int) $this->db->table('auto_numbering_counters')->countAllResults());
    }

    /** @param array<string, mixed> $series */
    private function assertInvalid(CanonicalNumberingPatternValidator $validator, string $pattern, array $series = []): void
    {
        try {
            $validator->parse($pattern, $series);
            $this->fail('Expected pattern rejection for ' . $pattern);
        } catch (DomainException $exception) {
            $this->assertSame(CanonicalNumberingPatternValidator::ERROR_PATTERN_INVALID, $exception->getErrorCode());
        }
    }

    /** @param list<array<string, mixed>> $series */
    private function assertInvalidCollision(CanonicalNumberingPatternValidator $validator, array $series): void
    {
        $this->expectExceptionMessage(CanonicalNumberingPatternValidator::ERROR_PATTERN_COLLISION);
        $validator->validateSeriesSet($series);
    }

    /** @return array<string, mixed> */
    private function series(string $scope, string $reset, ?string $namespace = null): array
    {
        $series = [
            'resource_id' => 1,
            'scope_type' => $scope,
            'reset_policy' => $reset,
            'status' => 'ACTIVE',
            'pattern' => '{SEQ:4}',
        ];
        if ($namespace !== null) {
            $series['identifier_uniqueness_scope'] = $namespace;
        }

        return $series;
    }

    private function resource(string $type): int
    {
        $this->db->table('resources')->insert([
            'type' => $type . '-' . uniqid(),
            'name' => $type,
            'domain' => 'SYSTEM',
            'created_at' => '2026-10-06 04:05:06',
        ]);

        return (int) $this->db->insertID();
    }

    private function operation(): OperationContext
    {
        return new OperationContext(1, new EvaluationTime(new DateTimeImmutable('2026-10-06 04:05:06', new DateTimeZone('UTC'))), 'num002-test', false);
    }
}

final class Num002AllowAuthorization implements NumberingAuthorizationPort
{
    public function authorize(OperationContext $operation, string $resourceType, string $action, EvaluationOrganizationRequest $contextRequest, mixed $stateId = null): AuthorizationDecision
    {
        return new AuthorizationDecision('ALLOW', 'AUTH_POLICY_ALLOWED', $operation->actorUserId ?? 1, $resourceType, $action, 1, null, $operation->evaluationTime, [], [], [], [], $operation->requestId);
    }
}
