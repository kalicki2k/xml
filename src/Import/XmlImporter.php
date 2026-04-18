<?php

declare(strict_types=1);

namespace Kalle\Xml\Import;

use DOMAttr;
use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMEntityReference;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Exception\ImportException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\Node;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;
use Kalle\Xml\Reader\ReaderDocument;
use Kalle\Xml\Reader\ReaderElement;

use function sprintf;
use function str_contains;
use function trim;

final class XmlImporter
{
    private function __construct() {}

    public static function document(ReaderDocument $document): XmlDocument
    {
        $domDocument = $document->toDomDocument();

        self::assertImportableDocument($domDocument);

        return new XmlDocument(
            self::importDomElement(self::requireDocumentElement($domDocument), true),
            self::importDeclaration($domDocument),
        );
    }

    public static function element(ReaderElement $element): Element
    {
        return self::importDomElement($element->toDomElement(), true);
    }

    private static function importDeclaration(DOMDocument $document): ?XmlDeclaration
    {
        $encoding = $document->xmlEncoding;
        $standalone = $document->xmlStandalone === true ? true : null;

        if ($encoding === null && $standalone === null) {
            return null;
        }

        return new XmlDeclaration(
            $document->xmlVersion !== null && $document->xmlVersion !== '' ? $document->xmlVersion : '1.0',
            $encoding,
            $standalone,
        );
    }

    private static function assertImportableDocument(DOMDocument $document): void
    {
        foreach ($document->childNodes as $child) {
            if ($child instanceof DOMElement) {
                continue;
            }

            if ($child instanceof DOMText && trim($child->nodeValue ?? '') === '') {
                continue;
            }

            if ($child instanceof DOMDocumentType) {
                throw new ImportException(
                    'XmlImporter cannot import XML documents with a DOCTYPE declaration.',
                );
            }

            if ($child instanceof DOMComment) {
                throw new ImportException(
                    'XmlImporter cannot import document-level comments.',
                );
            }

            if ($child instanceof DOMProcessingInstruction) {
                throw new ImportException(
                    'XmlImporter cannot import document-level processing instructions.',
                );
            }

            throw new ImportException(sprintf(
                'XmlImporter cannot import document-level node type "%s".',
                $child::class,
            ));
        }
    }

    private static function requireDocumentElement(DOMDocument $document): DOMElement
    {
        $rootElement = $document->documentElement;

        if ($rootElement instanceof DOMElement) {
            return $rootElement;
        }

        throw new ImportException('XmlImporter requires an XML document with a document element.');
    }

    private static function importDomElement(DOMElement $element, bool $isImportedRoot = false): Element
    {
        $attributes = [];
        $namespaceDeclarations = $isImportedRoot ? self::collectRootNamespaceDeclarations($element) : [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = new Attribute(
                self::qualifiedNameFromAttribute($attribute),
                $attribute->value,
            );
        }

        $children = [];

        foreach ($element->childNodes as $child) {
            $importedChild = self::importDomNode($child);

            if ($importedChild === null) {
                continue;
            }

            $children[] = $importedChild;
        }

        return new Element(
            self::qualifiedNameFromElement($element),
            $attributes,
            $children,
            $namespaceDeclarations,
        );
    }

    private static function importDomNode(DOMNode $node): ?Node
    {
        if ($node instanceof DOMElement) {
            return self::importDomElement($node);
        }

        if ($node instanceof DOMCdataSection) {
            return new CDataNode($node->data);
        }

        if ($node instanceof DOMText) {
            $content = $node->nodeValue ?? '';

            if ($content === '' || self::isFormattingWhitespace($node)) {
                return null;
            }

            return new TextNode($content);
        }

        if ($node instanceof DOMComment) {
            return new CommentNode($node->data);
        }

        if ($node instanceof DOMProcessingInstruction) {
            return new ProcessingInstructionNode($node->target, $node->data);
        }

        if ($node instanceof DOMEntityReference) {
            throw new ImportException(sprintf(
                'XmlImporter cannot import entity references such as "&%s;".',
                $node->nodeName,
            ));
        }

        throw new ImportException(sprintf(
            'XmlImporter cannot import node type "%s" in element content.',
            $node::class,
        ));
    }

    /**
     * @return list<NamespaceDeclaration>
     */
    private static function collectRootNamespaceDeclarations(DOMElement $element): array
    {
        $declarations = [];
        $elementName = self::qualifiedNameFromElement($element);

        if ($elementName->prefix() === null && $elementName->namespaceUri() !== null) {
            $declarations[''] = new NamespaceDeclaration(null, $elementName->namespaceUri());
        }

        $rootPrefixUsages = [];
        self::collectDirectPrefixUsages($element, $rootPrefixUsages);

        $subtreePrefixUsages = [];
        self::collectPrefixedNamespaceUsages($element, $subtreePrefixUsages);

        foreach ($subtreePrefixUsages as $prefix => $uris) {
            if (isset($declarations[$prefix])) {
                continue;
            }

            if ($uris === []) {
                continue;
            }

            if (count($uris) === 1) {
                $declarations[$prefix] = new NamespaceDeclaration($prefix, $uris[0]);
                continue;
            }

            $rootUri = $rootPrefixUsages[$prefix] ?? null;

            if ($rootUri !== null) {
                $declarations[$prefix] = new NamespaceDeclaration($prefix, $rootUri);
            }
        }

        return array_values($declarations);
    }

    /**
     * @param array<string, string> $prefixUsages
     */
    private static function collectDirectPrefixUsages(DOMElement $element, array &$prefixUsages): void
    {
        self::collectQualifiedNamePrefixUsage(self::qualifiedNameFromElement($element), $prefixUsages);

        foreach ($element->attributes as $attribute) {
            self::collectQualifiedNamePrefixUsage(
                self::qualifiedNameFromAttribute($attribute),
                $prefixUsages,
            );
        }
    }

    /**
     * @param array<string, list<string>> $prefixUsages
     */
    private static function collectPrefixedNamespaceUsages(DOMElement $element, array &$prefixUsages): void
    {
        self::collectQualifiedNamePrefixSetUsage(self::qualifiedNameFromElement($element), $prefixUsages);

        foreach ($element->attributes as $attribute) {
            self::collectQualifiedNamePrefixSetUsage(
                self::qualifiedNameFromAttribute($attribute),
                $prefixUsages,
            );
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                self::collectPrefixedNamespaceUsages($child, $prefixUsages);
            }
        }
    }

    /**
     * @param array<string, string> $prefixUsages
     */
    private static function collectQualifiedNamePrefixUsage(
        QualifiedName $name,
        array &$prefixUsages,
    ): void {
        $prefix = $name->prefix();
        $namespaceUri = $name->namespaceUri();

        if ($prefix === null || $namespaceUri === null) {
            return;
        }

        if ($prefix === 'xml' && $namespaceUri === QualifiedName::XML_NAMESPACE_URI) {
            return;
        }

        $prefixUsages[$prefix] = $namespaceUri;
    }

    /**
     * @param array<string, list<string>> $prefixUsages
     */
    private static function collectQualifiedNamePrefixSetUsage(
        QualifiedName $name,
        array &$prefixUsages,
    ): void {
        $prefix = $name->prefix();
        $namespaceUri = $name->namespaceUri();

        if ($prefix === null || $namespaceUri === null) {
            return;
        }

        if ($prefix === 'xml' && $namespaceUri === QualifiedName::XML_NAMESPACE_URI) {
            return;
        }

        $knownUris = $prefixUsages[$prefix] ?? [];

        if (!in_array($namespaceUri, $knownUris, true)) {
            $knownUris[] = $namespaceUri;
        }

        $prefixUsages[$prefix] = $knownUris;
    }

    private static function isFormattingWhitespace(DOMText $text): bool
    {
        $content = $text->nodeValue ?? '';

        if (trim($content) !== '') {
            return false;
        }

        if (!str_contains($content, "\n") && !str_contains($content, "\r")) {
            return false;
        }

        $parent = $text->parentNode;

        if (!$parent instanceof DOMElement) {
            return false;
        }

        foreach ($parent->childNodes as $sibling) {
            if (
                $sibling instanceof DOMElement
                || $sibling instanceof DOMComment
                || $sibling instanceof DOMCdataSection
                || $sibling instanceof DOMProcessingInstruction
                || $sibling instanceof DOMEntityReference
            ) {
                return true;
            }
        }

        return false;
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
