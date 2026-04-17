<?php

declare(strict_types=1);

namespace Kalle\Xml\Namespace;

use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Validate\XmlNameValidator;

final readonly class NamespaceDeclaration
{
    public function __construct(
        private ?string $prefix,
        private string $uri,
    ) {
        if ($this->prefix === '') {
            throw new InvalidNamespaceDeclarationException(
                'Namespace prefix cannot be empty. Use null for the default namespace.',
            );
        }

        if ($this->prefix !== null) {
            XmlNameValidator::assertValidNamespacePrefix($this->prefix, 'Namespace declaration');
        }

        XmlEscaper::assertValidString($this->uri, 'Namespace URI');

        if ($this->prefix !== null && $this->uri === '') {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Prefix "%s" cannot be undeclared in XML 1.0.',
                $this->prefix,
            ));
        }

        if ($this->prefix === 'xmlns') {
            throw new InvalidNamespaceDeclarationException(
                'Prefix "xmlns" is reserved for namespace declarations.',
            );
        }

        if ($this->uri === QualifiedName::XMLNS_NAMESPACE_URI) {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Namespace URI "%s" is reserved for namespace declarations.',
                QualifiedName::XMLNS_NAMESPACE_URI,
            ));
        }

        if ($this->prefix === 'xml' && $this->uri !== QualifiedName::XML_NAMESPACE_URI) {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Prefix "xml" must use namespace URI "%s".',
                QualifiedName::XML_NAMESPACE_URI,
            ));
        }

        if ($this->uri === QualifiedName::XML_NAMESPACE_URI && $this->prefix !== 'xml') {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Namespace URI "%s" can only be bound to prefix "xml".',
                QualifiedName::XML_NAMESPACE_URI,
            ));
        }

        if ($this->uri === QualifiedName::XML_NAMESPACE_URI && $this->prefix === null) {
            throw new InvalidNamespaceDeclarationException(
                'The XML namespace cannot be declared as the default namespace.',
            );
        }
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function isDefault(): bool
    {
        return $this->prefix === null;
    }

    public function prefixKey(): string
    {
        return $this->prefix ?? '';
    }

    public function attributeName(): string
    {
        if ($this->prefix === null) {
            return 'xmlns';
        }

        return 'xmlns:' . $this->prefix;
    }
}
