<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\StreamingReaderException;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Validation\ValidationResult;
use Kalle\Xml\Validation\XmlValidator;

use function is_string;

final readonly class StreamedElement
{
    private function __construct(
        private ReaderElement $readerElement,
    ) {}

    /**
     * @internal Internal bridge from StreamingXmlReader::readElements().
     */
    public static function fromReaderElement(ReaderElement $readerElement): self
    {
        return new self($readerElement);
    }

    public function name(): string
    {
        return $this->readerElement->name();
    }

    public function qualifiedName(): QualifiedName
    {
        return $this->readerElement->qualifiedName();
    }

    public function localName(): string
    {
        return $this->readerElement->localName();
    }

    public function prefix(): ?string
    {
        return $this->readerElement->prefix();
    }

    public function namespaceUri(): ?string
    {
        return $this->readerElement->namespaceUri();
    }

    /**
     * @return list<Attribute>
     */
    public function attributes(): array
    {
        return $this->readerElement->attributes();
    }

    public function hasAttribute(string|QualifiedName $name): bool
    {
        return $this->readerElement->hasAttribute($name);
    }

    public function attribute(string|QualifiedName $name): ?Attribute
    {
        return $this->readerElement->attribute($name);
    }

    public function attributeValue(string|QualifiedName $name): ?string
    {
        return $this->readerElement->attributeValue($name);
    }

    /**
     * Enters the regular read-only subtree model for traversal and queries.
     */
    public function toReaderElement(): ReaderElement
    {
        return $this->readerElement;
    }

    /**
     * Serializes the selected subtree as XML without a declaration.
     */
    public function toXmlString(): string
    {
        $element = $this->readerElement->toDomElement();
        $document = $element->ownerDocument;

        if (!$document instanceof DOMDocument) {
            throw new StreamingReaderException(
                'StreamedElement::toXmlString() could not access the materialized owner document.',
            );
        }

        $xml = $document->saveXML($element);

        if (!is_string($xml)) {
            throw new StreamingReaderException(
                'StreamedElement::toXmlString() could not serialize the streamed element subtree.',
            );
        }

        return $xml;
    }

    /**
     * Converts the selected subtree into the immutable writer-side element model.
     */
    public function toWriterElement(): Element
    {
        return XmlImporter::element($this->readerElement);
    }

    /**
     * Thin shorthand for $validator->validateString($streamedElement->toXmlString()).
     */
    public function validate(XmlValidator $validator): ValidationResult
    {
        return $validator->validateString($this->toXmlString());
    }
}
