<?php

declare(strict_types=1);

namespace Kalle\Xml\Canonicalization;

final readonly class CanonicalizationOptions
{
    public function __construct(
        private bool $includeComments = false,
    ) {}

    public static function withoutComments(): self
    {
        return new self(false);
    }

    public static function withComments(): self
    {
        return new self(true);
    }

    public function includeComments(): bool
    {
        return $this->includeComments;
    }

    public function withIncludeComments(bool $includeComments): self
    {
        if ($includeComments === $this->includeComments) {
            return $this;
        }

        return new self($includeComments);
    }
}
