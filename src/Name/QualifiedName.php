<?php

declare(strict_types=1);

namespace Kalle\Xml\Name;

use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\InvalidXmlName;
use Kalle\Xml\Validate\XmlNameValidator;

final readonly class QualifiedName
{
    public const XML_NAMESPACE_URI = 'http://www.w3.org/XML/1998/namespace';
    public const XMLNS_NAMESPACE_URI = 'http://www.w3.org/2000/xmlns/';

    public function __construct(
        private string $localName,
        private ?string $namespaceUri = null,
        private ?string $prefix = null,
    ) {
        XmlNameValidator::assertValidLocalName($this->localName, 'Qualified name');

        if ($this->prefix !== null) {
            XmlNameValidator::assertValidNamespacePrefix($this->prefix, 'Qualified name');
        }

        if ($this->namespaceUri !== null) {
            XmlEscaper::assertValidString($this->namespaceUri, 'Namespace URI');

            if ($this->namespaceUri === '') {
                throw new InvalidXmlName('Namespace URI cannot be empty.');
            }
        }

        if ($this->prefix !== null && $this->namespaceUri === null) {
            throw new InvalidXmlName(sprintf(
                'Prefix "%s" requires a namespace URI.',
                $this->prefix,
            ));
        }

        if ($this->prefix === 'xmlns' || $this->localName === 'xmlns') {
            throw new InvalidXmlName(
                '"xmlns" is reserved for namespace declarations.',
            );
        }

        if ($this->namespaceUri === self::XMLNS_NAMESPACE_URI) {
            throw new InvalidXmlName(sprintf(
                'Namespace URI "%s" is reserved for namespace declarations.',
                self::XMLNS_NAMESPACE_URI,
            ));
        }

        if ($this->prefix === 'xml' && $this->namespaceUri !== self::XML_NAMESPACE_URI) {
            throw new InvalidXmlName(sprintf(
                'Prefix "xml" must use namespace URI "%s".',
                self::XML_NAMESPACE_URI,
            ));
        }

        if ($this->namespaceUri === self::XML_NAMESPACE_URI && $this->prefix !== 'xml') {
            throw new InvalidXmlName(sprintf(
                'Namespace URI "%s" can only be used with prefix "xml".',
                self::XML_NAMESPACE_URI,
            ));
        }
    }

    public static function forElement(string|self $name): self
    {
        if ($name instanceof self) {
            return $name;
        }

        XmlNameValidator::assertValidElementName($name);

        return new self($name);
    }

    public static function forAttribute(string|self $name): self
    {
        if (!$name instanceof self) {
            XmlNameValidator::assertValidAttributeName($name);
            $name = new self($name);
        }

        if ($name->namespaceUri() !== null && $name->prefix() === null) {
            throw new InvalidXmlName(
                'Namespaced attributes require an explicit prefix. Default namespaces do not apply to attributes.',
            );
        }

        return $name;
    }

    public function localName(): string
    {
        return $this->localName;
    }

    public function namespaceUri(): ?string
    {
        return $this->namespaceUri;
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function lexicalName(): string
    {
        if ($this->prefix === null) {
            return $this->localName;
        }

        return $this->prefix . ':' . $this->localName;
    }

    public function identityKey(): string
    {
        if ($this->namespaceUri === null) {
            return $this->localName;
        }

        return sprintf('{%s}%s', $this->namespaceUri, $this->localName);
    }

    public function __toString(): string
    {
        return $this->lexicalName();
    }
}
