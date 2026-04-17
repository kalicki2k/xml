<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;

use function ksort;
use function str_starts_with;
use function substr;

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
        $document = $this->element->ownerDocument;

        if (!$document instanceof DOMDocument) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $namespaceNodes = $xpath->query('namespace::*', $this->element);

        if ($namespaceNodes === false) {
            return [];
        }

        $declarations = [];

        foreach ($namespaceNodes as $namespaceNode) {
            $nodeName = $namespaceNode->nodeName;

            if ($nodeName === 'xmlns') {
                $prefix = null;
            } elseif (str_starts_with($nodeName, 'xmlns:')) {
                $prefix = substr($nodeName, 6);
            } else {
                continue;
            }

            if ($prefix === 'xml' && $namespaceNode->nodeValue === QualifiedName::XML_NAMESPACE_URI) {
                continue;
            }

            $declaration = new NamespaceDeclaration($prefix, $namespaceNode->nodeValue ?? '');

            $declarations[$declaration->prefixKey()] = $declaration;
        }

        $defaultDeclaration = $declarations[''] ?? null;
        unset($declarations['']);
        ksort($declarations);

        $sorted = [];

        if ($defaultDeclaration !== null) {
            $sorted[] = $defaultDeclaration;
        }

        foreach ($declarations as $declaration) {
            $sorted[] = $declaration;
        }

        return $sorted;
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
