<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use LogicException;

final readonly class ReaderDocument
{
    private function __construct(
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

        return new self(ReaderElement::fromDomElement($rootElement));
    }

    public function rootElement(): ReaderElement
    {
        return $this->rootElement;
    }
}
