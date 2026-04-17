<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Exception\InvalidWriterConfigException;

use function in_array;
use function preg_match;

final readonly class WriterConfig
{
    public function __construct(
        private bool $prettyPrint = false,
        private string $indent = '    ',
        private string $newline = "\n",
        private bool $emitDeclaration = true,
        private bool $selfCloseEmptyElements = true,
    ) {
        if ($this->indent === '') {
            throw new InvalidWriterConfigException('Indent cannot be empty.');
        }

        if (preg_match('/^[ \t]+$/', $this->indent) !== 1) {
            throw new InvalidWriterConfigException('Indent must contain only spaces or tabs.');
        }

        if (!in_array($this->newline, ["\n", "\r\n", "\r"], true)) {
            throw new InvalidWriterConfigException(
                'Newline must be "\\n", "\\r\\n", or "\\r".',
            );
        }
    }

    public static function compact(
        bool $emitDeclaration = true,
        bool $selfCloseEmptyElements = true,
    ): self {
        return new self(
            prettyPrint: false,
            emitDeclaration: $emitDeclaration,
            selfCloseEmptyElements: $selfCloseEmptyElements,
        );
    }

    public static function pretty(
        string $indent = '    ',
        string $newline = "\n",
        bool $emitDeclaration = true,
        bool $selfCloseEmptyElements = true,
    ): self {
        return new self(
            prettyPrint: true,
            indent: $indent,
            newline: $newline,
            emitDeclaration: $emitDeclaration,
            selfCloseEmptyElements: $selfCloseEmptyElements,
        );
    }

    public function prettyPrint(): bool
    {
        return $this->prettyPrint;
    }

    public function indent(): string
    {
        return $this->indent;
    }

    public function newline(): string
    {
        return $this->newline;
    }

    public function emitDeclaration(): bool
    {
        return $this->emitDeclaration;
    }

    public function selfCloseEmptyElements(): bool
    {
        return $this->selfCloseEmptyElements;
    }

    public function withPrettyPrint(bool $prettyPrint): self
    {
        if ($prettyPrint === $this->prettyPrint) {
            return $this;
        }

        return $this->rebuild(prettyPrint: $prettyPrint);
    }

    public function withIndent(string $indent): self
    {
        if ($indent === $this->indent) {
            return $this;
        }

        return $this->rebuild(indent: $indent);
    }

    public function withNewline(string $newline): self
    {
        if ($newline === $this->newline) {
            return $this;
        }

        return $this->rebuild(newline: $newline);
    }

    public function withEmitDeclaration(bool $emitDeclaration): self
    {
        if ($emitDeclaration === $this->emitDeclaration) {
            return $this;
        }

        return $this->rebuild(emitDeclaration: $emitDeclaration);
    }

    public function withSelfCloseEmptyElements(bool $selfCloseEmptyElements): self
    {
        if ($selfCloseEmptyElements === $this->selfCloseEmptyElements) {
            return $this;
        }

        return $this->rebuild(selfCloseEmptyElements: $selfCloseEmptyElements);
    }

    private function rebuild(
        ?bool $prettyPrint = null,
        ?string $indent = null,
        ?string $newline = null,
        ?bool $emitDeclaration = null,
        ?bool $selfCloseEmptyElements = null,
    ): self {
        return new self(
            $prettyPrint ?? $this->prettyPrint,
            $indent ?? $this->indent,
            $newline ?? $this->newline,
            $emitDeclaration ?? $this->emitDeclaration,
            $selfCloseEmptyElements ?? $this->selfCloseEmptyElements,
        );
    }
}
