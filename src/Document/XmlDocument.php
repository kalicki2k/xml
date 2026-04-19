<?php

declare(strict_types=1);

namespace Kalle\Xml\Document;

use Kalle\Xml\Node\Element;

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
}
