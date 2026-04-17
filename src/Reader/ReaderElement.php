<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMAttr;
use DOMElement;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;

final readonly class ReaderElement
{
    private function __construct(
        private DOMElement $element,
        private QualifiedName $qualifiedName,
    ) {}

    /**
     * @internal Internal bridge from DOM-backed loading to the public reader model.
     */
    public static function fromDomElement(DOMElement $element): self
    {
        return new self($element, self::qualifiedNameFromElement($element));
    }

    public function name(): string
    {
        return $this->qualifiedName->lexicalName();
    }

    public function qualifiedName(): QualifiedName
    {
        return $this->qualifiedName;
    }

    /**
     * @internal Internal bridge for importer support on top of the reader model.
     */
    public function toDomElement(): DOMElement
    {
        return $this->element;
    }

    public function localName(): string
    {
        return $this->qualifiedName->localName();
    }

    public function prefix(): ?string
    {
        return $this->qualifiedName->prefix();
    }

    public function namespaceUri(): ?string
    {
        return $this->qualifiedName->namespaceUri();
    }

    public function text(): string
    {
        return $this->element->textContent;
    }

    public function parent(): ?self
    {
        $parent = $this->element->parentNode;

        if (!$parent instanceof DOMElement) {
            return null;
        }

        return self::fromDomElement($parent);
    }

    /**
     * @return list<self>
     */
    public function childElements(string|QualifiedName|null $name = null): array
    {
        $qualifiedName = $name !== null ? QualifiedName::forElement($name) : null;
        $children = [];

        foreach ($this->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $readerElement = self::fromDomElement($child);

            if (
                $qualifiedName === null
                || $readerElement->qualifiedName()->identityKey() === $qualifiedName->identityKey()
            ) {
                $children[] = $readerElement;
            }
        }

        return $children;
    }

    public function firstChildElement(string|QualifiedName|null $name = null): ?self
    {
        $qualifiedName = $name !== null ? QualifiedName::forElement($name) : null;

        foreach ($this->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $readerElement = self::fromDomElement($child);

            if (
                $qualifiedName === null
                || $readerElement->qualifiedName()->identityKey() === $qualifiedName->identityKey()
            ) {
                return $readerElement;
            }
        }

        return null;
    }

    /**
     * @return list<Attribute>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->element->attributes as $attribute) {
            if ($attribute->namespaceURI === QualifiedName::XMLNS_NAMESPACE_URI) {
                continue;
            }

            $attributes[] = new Attribute(
                self::qualifiedNameFromAttribute($attribute),
                $attribute->value,
            );
        }

        return $attributes;
    }

    /**
     * @return list<NamespaceDeclaration>
     */
    public function namespacesInScope(): array
    {
        return DomNamespaceInspector::namespacesInScope($this->element);
    }

    public function hasAttribute(string|QualifiedName $name): bool
    {
        return $this->attribute($name) !== null;
    }

    public function attribute(string|QualifiedName $name): ?Attribute
    {
        $qualifiedName = QualifiedName::forAttribute($name);
        $attribute = $qualifiedName->namespaceUri() === null
            ? $this->element->getAttributeNode($qualifiedName->localName())
            : $this->element->getAttributeNodeNS(
                $qualifiedName->namespaceUri(),
                $qualifiedName->localName(),
            );

        if (!$attribute instanceof DOMAttr || $attribute->namespaceURI === QualifiedName::XMLNS_NAMESPACE_URI) {
            return null;
        }

        return new Attribute(
            self::qualifiedNameFromAttribute($attribute),
            $attribute->value,
        );
    }

    public function attributeValue(string|QualifiedName $name): ?string
    {
        return $this->attribute($name)?->value();
    }

    /**
     * @param array<string, string> $namespaces
     *
     * @return list<self>
     */
    public function findAll(string $expression, array $namespaces = []): array
    {
        return XPathQuery::forElement($this->element)->findAll($expression, $namespaces);
    }

    /**
     * @param array<string, string> $namespaces
     */
    public function findFirst(string $expression, array $namespaces = []): ?self
    {
        return XPathQuery::forElement($this->element)->findFirst($expression, $namespaces);
    }

    private static function qualifiedNameFromElement(DOMElement $element): QualifiedName
    {
        return new QualifiedName(
            $element->localName ?? $element->tagName,
            $element->namespaceURI !== '' ? $element->namespaceURI : null,
            $element->prefix !== '' ? $element->prefix : null,
        );
    }

    private static function qualifiedNameFromAttribute(DOMAttr $attribute): QualifiedName
    {
        return new QualifiedName(
            $attribute->localName ?? $attribute->name,
            $attribute->namespaceURI !== '' ? $attribute->namespaceURI : null,
            $attribute->prefix !== '' ? $attribute->prefix : null,
        );
    }
}
