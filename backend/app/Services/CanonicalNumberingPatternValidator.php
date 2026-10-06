<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainException;

/** Pure validator for the canonical P1 Auto Numbering pattern language. */
final class CanonicalNumberingPatternValidator implements NumberingPatternCompatibilityValidator
{
    public const ERROR_PATTERN_INVALID = 'NUM_PATTERN_INVALID';
    public const ERROR_PATTERN_COLLISION = 'NUM_PATTERN_COLLISION';

    /** @var array<string, true> */
    private const SIMPLE_TOKENS = [
        'YYYY' => true,
        'YY' => true,
        'MM' => true,
        'DD' => true,
        'COMPANY_CODE' => true,
        'ORG_CODE' => true,
        'RESOURCE' => true,
    ];

    /** @var list<string> */
    private const RESET_POLICIES = ['NEVER', 'YEARLY', 'MONTHLY', 'DAILY'];

    /** @var list<string> */
    private const SCOPES = ['GROUP', 'COMPANY', 'ORGANIZATION'];

    /** @var list<string> */
    private const NAMESPACES = ['GLOBAL', 'COMPANY', 'ORGANIZATION'];

    /**
     * Parse and validate one pattern plus the static metadata available for its
     * series. No database, organization, clock, or counter is consulted.
     *
     * @param array<string, mixed> $series
     */
    public function parse(string $pattern, array $series = []): ParsedNumberingPattern
    {
        $segments = [];
        $tokens = [];
        $dateTokens = [];
        $sequenceWidth = null;
        $minimumLength = 0;
        $offset = 0;
        $length = strlen($pattern);

        while ($offset < $length) {
            $open = strpos($pattern, '{', $offset);
            $close = strpos($pattern, '}', $offset);
            if ($close !== false && ($open === false || $close < $open)) {
                $this->invalid('unbalanced_brace');
            }
            if ($open === false) {
                $this->appendLiteral($segments, substr($pattern, $offset));
                $minimumLength += $length - $offset;
                break;
            }
            if ($open > $offset) {
                $literal = substr($pattern, $offset, $open - $offset);
                $this->appendLiteral($segments, $literal);
                $minimumLength += strlen($literal);
            }

            $close = strpos($pattern, '}', $open + 1);
            $nestedOpen = strpos($pattern, '{', $open + 1);
            if ($close === false || ($nestedOpen !== false && $nestedOpen < $close)) {
                $this->invalid('unbalanced_brace');
            }
            $expression = substr($pattern, $open + 1, $close - $open - 1);
            if (preg_match('/^SEQ:([0-9]+)$/', $expression, $match) === 1) {
                if ($sequenceWidth !== null) {
                    $this->invalid('multiple_sequence_tokens');
                }
                $width = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($width === false) {
                    $this->invalid('sequence_width');
                }
                $sequenceWidth = $width;
                $this->appendToken($segments, '{' . $expression . '}', 'SEQ', $width);
                $tokens[] = 'SEQ';
                $minimumLength += $width;
            } elseif (isset(self::SIMPLE_TOKENS[$expression])) {
                $this->appendToken($segments, '{' . $expression . '}', $expression);
                if (! in_array($expression, $tokens, true)) {
                    $tokens[] = $expression;
                }
                if (in_array($expression, ['YYYY', 'YY', 'MM', 'DD'], true)) {
                    if (! in_array($expression, $dateTokens, true)) {
                        $dateTokens[] = $expression;
                    }
                    $minimumLength += match ($expression) {
                        'YYYY' => 4,
                        default => 2,
                    };
                } else {
                    $minimumLength++;
                }
            } else {
                $this->invalid('unknown_token', $expression);
            }
            $offset = $close + 1;
        }

        if ($sequenceWidth === null) {
            $this->invalid('sequence_required');
        }

        $parsed = new ParsedNumberingPattern(
            normalizedPattern: $pattern,
            segments: $segments,
            sequenceWidth: $sequenceWidth,
            tokens: $tokens,
            dateTokens: $dateTokens,
            hasDateDiscriminator: $dateTokens !== [],
            hasCompanyDiscriminator: in_array('COMPANY_CODE', $tokens, true),
            hasOrganizationDiscriminator: in_array('ORG_CODE', $tokens, true),
            minimumRenderedLength: $minimumLength,
        );
        $this->validateSeriesSemantics($parsed, $series);

        return $parsed;
    }

    /** @param array<string, mixed> $series */
    public function validate(string $pattern, array $series): void
    {
        $this->parse($pattern, $series);
    }

    /**
     * Validate a set of series without resolving or rendering any runtime value.
     * Only deterministic structural collisions are rejected.
     *
     * @param list<array<string, mixed>> $series
     */
    public function validateSeriesSet(array $series): void
    {
        $parsed = [];
        foreach ($series as $index => $candidate) {
            $status = isset($candidate['status']) ? strtoupper((string) $candidate['status']) : 'ACTIVE';
            if ($status !== 'ACTIVE' || (array_key_exists('selectable', $candidate) && ! (bool) $candidate['selectable'])) {
                continue;
            }
            $parsed[$index] = $this->parse((string) ($candidate['pattern'] ?? ''), $candidate);
        }

        $active = array_keys($parsed);
        for ($left = 0, $count = count($active); $left < $count; $left++) {
            $leftIndex = $active[$left];
            for ($right = $left + 1; $right < $count; $right++) {
                $rightIndex = $active[$right];
                $a = $series[$leftIndex];
                $b = $series[$rightIndex];
                if (isset($a['resource_id'], $b['resource_id']) && (string) $a['resource_id'] !== (string) $b['resource_id']) {
                    continue;
                }
                $namespaceA = $this->optionalUpper($a, 'identifier_uniqueness_scope');
                $namespaceB = $this->optionalUpper($b, 'identifier_uniqueness_scope');
                if ($namespaceA !== $namespaceB) {
                    continue;
                }
                if ($parsed[$leftIndex]->collisionSignature() !== $parsed[$rightIndex]->collisionSignature()) {
                    continue;
                }
                throw new DomainException(self::ERROR_PATTERN_COLLISION, [
                    'field' => 'pattern',
                    'series_index' => $rightIndex,
                    'conflicts_with' => $leftIndex,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $series */
    private function validateSeriesSemantics(ParsedNumberingPattern $parsed, array $series): void
    {
        if (isset($series['reset_policy'])) {
            $reset = strtoupper((string) $series['reset_policy']);
            if (! in_array($reset, self::RESET_POLICIES, true)) {
                $this->invalid('reset_policy');
            }
            if ($reset === 'YEARLY' && ! $this->hasAny($parsed, ['YYYY', 'YY'])) {
                $this->invalid('year_discriminator_required');
            }
            if ($reset === 'MONTHLY' && (! $this->hasAny($parsed, ['YYYY', 'YY']) || ! $parsed->hasToken('MM'))) {
                $this->invalid('month_discriminator_required');
            }
            if ($reset === 'DAILY' && (! $this->hasAny($parsed, ['YYYY', 'YY']) || ! $parsed->hasToken('MM') || ! $parsed->hasToken('DD'))) {
                $this->invalid('day_discriminator_required');
            }
        }

        if (isset($series['scope_type'])) {
            $scope = strtoupper((string) $series['scope_type']);
            if (! in_array($scope, self::SCOPES, true)) {
                $this->invalid('scope_type');
            }
            if ($scope === 'GROUP' && $parsed->hasCompanyDiscriminator) {
                $this->invalid('company_discriminator_requires_company_scope');
            }
        }

        $namespace = $this->optionalUpper($series, 'identifier_uniqueness_scope');
        if ($namespace !== null) {
            if (! in_array($namespace, self::NAMESPACES, true)) {
                $this->invalid('identifier_uniqueness_scope');
            }
            $scope = strtoupper((string) ($series['scope_type'] ?? ''));
            if ($namespace === 'GLOBAL' && $scope === 'COMPANY' && ! $parsed->hasCompanyDiscriminator) {
                $this->invalid('company_discriminator_required');
            }
            if ($namespace === 'GLOBAL' && $scope === 'ORGANIZATION' && ! $parsed->hasOrganizationDiscriminator) {
                $this->invalid('organization_discriminator_required');
            }
            if ($namespace === 'COMPANY' && $scope === 'ORGANIZATION' && ! $parsed->hasOrganizationDiscriminator) {
                $this->invalid('organization_discriminator_required');
            }
        }

        if (isset($series['identifier_max_length'])) {
            $maxLength = filter_var($series['identifier_max_length'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($maxLength === false || $parsed->minimumRenderedLength > $maxLength) {
                $this->invalid('identifier_length');
            }
        }
    }

    /** @param list<string> $tokens */
    private function hasAny(ParsedNumberingPattern $parsed, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($parsed->hasToken($token)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $series */
    private function optionalUpper(array $series, string $field): ?string
    {
        if (! array_key_exists($field, $series) || $series[$field] === null || $series[$field] === '') {
            return null;
        }

        return strtoupper((string) $series[$field]);
    }

    /** @param list<array{type: 'literal'|'token', value: string, token?: string, name?: string, width?: int}> $segments */
    private function appendLiteral(array &$segments, string $literal): void
    {
        if ($literal === '') {
            return;
        }
        $last = array_key_last($segments);
        if ($last !== null && $segments[$last]['type'] === 'literal') {
            $segments[$last]['value'] .= $literal;
            return;
        }
        $segments[] = ['type' => 'literal', 'value' => $literal];
    }

    /** @param list<array{type: 'literal'|'token', value: string, token?: string, name?: string, width?: int}> $segments */
    private function appendToken(array &$segments, string $value, string $name, ?int $width = null): void
    {
        $segment = ['type' => 'token', 'value' => $value, 'token' => $value, 'name' => $name];
        if ($width !== null) {
            $segment['width'] = $width;
        }
        $segments[] = $segment;
    }

    private function invalid(string $reason, ?string $value = null): never
    {
        $context = ['field' => 'pattern', 'reason' => $reason];
        if ($value !== null) {
            $context['token'] = $value;
        }
        throw new DomainException(self::ERROR_PATTERN_INVALID, $context);
    }
}
