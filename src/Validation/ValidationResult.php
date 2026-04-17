<?php

declare(strict_types=1);

namespace Kalle\Xml\Validation;

final readonly class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    private function __construct(
        private bool $valid,
        private array $errors,
    ) {}

    public static function valid(): self
    {
        return new self(true, []);
    }

    /**
     * @param list<ValidationError> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?ValidationError
    {
        return $this->errors[0] ?? null;
    }
}
