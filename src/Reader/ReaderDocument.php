<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use LogicException;

final readonly class ReaderDocument
{
    private function __construct(
        private DOMDocument $document,
        private ReaderElement $rootElement,
    ) {}

    /**
     * @internal Internal bridge from DOM-backed loading to the public reader model.
     */
    public static function fromDomDocument(DOMDocument $document): self
    {
        $rootElement = $document->documentElement;

        if (!$rootElement instanceof DOMElement) {
            throw new LogicException('ReaderDocument requires a document element.');
        }

        return new self($document, ReaderElement::fromDomElement($rootElement));
    }

    public function rootElement(): ReaderElement
    {
        return $this->rootElement;
    }

    /**
     * @internal Internal bridge for importer support on top of the reader model.
     */
    public function toDomDocument(): DOMDocument
    {
        return $this->document;
    }

    /**
     * @param array<string, string> $namespaces
     *
     * @return list<ReaderElement>
     */
    public function findAll(string $expression, array $namespaces = []): array
    {
        return XPathQuery::forDocument($this->document)->findAll($expression, $namespaces);
    }

    /**
     * @param array<string, string> $namespaces
     */
    public function findFirst(string $expression, array $namespaces = []): ?ReaderElement
    {
        return XPathQuery::forDocument($this->document)->findFirst($expression, $namespaces);
    }
}
