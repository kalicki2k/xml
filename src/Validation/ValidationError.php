<?php

declare(strict_types=1);

namespace Kalle\Xml\Validation;

use LibXMLError;

use function sprintf;
use function trim;

final readonly class ValidationError
{
    public function __construct(
        private string $message,
        private ?int $level = null,
        private ?int $line = null,
        private ?int $column = null,
    ) {}

    public static function fromLibxmlError(LibXMLError $error): self
    {
        return new self(
            trim($error->message),
            $error->level > 0 ? $error->level : null,
            $error->line > 0 ? $error->line : null,
            $error->column > 0 ? $error->column : null,
        );
    }

    public function message(): string
    {
        return $this->message;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    public function level(): ?int
    {
        return $this->level;
    }

    public function column(): ?int
    {
        return $this->column;
    }

    public function __toString(): string
    {
        if ($this->line !== null && $this->column !== null) {
            return sprintf('Line %d, column %d: %s', $this->line, $this->column, $this->message);
        }

        if ($this->line !== null) {
            return sprintf('Line %d: %s', $this->line, $this->message);
        }

        return $this->message;
    }
}
