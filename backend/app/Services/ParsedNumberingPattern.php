<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Immutable syntax metadata for a canonical Auto Numbering pattern.
 *
 * The representation contains no resolved organization, time, or sequence
 * values. Those concerns belong to the later resolver and allocator tasks.
 */
final readonly class ParsedNumberingPattern
{
    /**
     * @param list<array{type: 'literal'|'token', value: string, token?: string, name?: string, width?: int}> $segments
     * @param list<string> $tokens
     * @param list<string> $dateTokens
     */
    public function __construct(
        public string $normalizedPattern,
        public array $segments,
        public int $sequenceWidth,
        public array $tokens,
        public array $dateTokens,
        public bool $hasDateDiscriminator,
        public bool $hasCompanyDiscriminator,
        public bool $hasOrganizationDiscriminator,
        public int $minimumRenderedLength,
    ) {
    }

    public function hasToken(string $token): bool
    {
        return in_array(strtoupper($token), $this->tokens, true);
    }

    /** @return list<string> */
    public function tokenNames(): array
    {
        return $this->tokens;
    }

    /** @return list<string> */
    public function tokensPresent(): array
    {
        return $this->tokens;
    }

    public function canonicalPattern(): string
    {
        return $this->normalizedPattern;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'normalized_pattern' => $this->normalizedPattern,
            'segments' => $this->segments,
            'sequence_width' => $this->sequenceWidth,
            'tokens' => $this->tokens,
            'tokens_present' => $this->tokens,
            'date_tokens' => $this->dateTokens,
            'has_date_discriminator' => $this->hasDateDiscriminator,
            'has_company_discriminator' => $this->hasCompanyDiscriminator,
            'has_organization_discriminator' => $this->hasOrganizationDiscriminator,
            'minimum_rendered_length' => $this->minimumRenderedLength,
        ];
    }

    /**
     * Sequence width is minimum padding, so width is deliberately absent from
     * this collision signature.
     */
    public function collisionSignature(): string
    {
        $parts = [];
        foreach ($this->segments as $segment) {
            $parts[] = $segment['type'] === 'literal'
                ? 'L:' . $segment['value']
                : 'T:' . ($segment['name'] ?? '');
        }

        return implode('|', $parts);
    }
}
