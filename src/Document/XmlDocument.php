<?php

declare(strict_types=1);

namespace Kalle\Xml\Document;

use Kalle\Xml\Node\Element;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

final readonly class XmlDocument
{
    public function __construct(
        private Element $root,
        private ?XmlDeclaration $declaration = null,
    ) {}

    public function root(): Element
    {
        return $this->root;
    }

    public function declaration(): ?XmlDeclaration
    {
        return $this->declaration;
    }

    public function withRoot(Element $root): self
    {
        if ($root === $this->root) {
            return $this;
        }

        return new self($root, $this->declaration);
    }

    public function withDeclaration(XmlDeclaration $declaration): self
    {
        if ($declaration === $this->declaration) {
            return $this;
        }

        return new self($this->root, $declaration);
    }

    public function withoutDeclaration(): self
    {
        if ($this->declaration === null) {
            return $this;
        }

        return new self($this->root, null);
    }

    public function toString(?WriterConfig $config = null): string
    {
        $writer = StreamingXmlWriter::forString($config);
        $writer->writeDocument($this);
        $writer->finish();

        return $writer->toString();
    }

    public function saveToFile(string $path, ?WriterConfig $config = null): void
    {
        $writer = StreamingXmlWriter::forFile($path, $config);
        $writer->writeDocument($this);
        $writer->finish();
    }

    public function saveToStream(mixed $stream, ?WriterConfig $config = null): void
    {
        $writer = StreamingXmlWriter::forStream($stream, $config);
        $writer->writeDocument($this);
        $writer->finish();
    }
}
