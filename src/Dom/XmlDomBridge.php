<?php

declare(strict_types=1);

namespace Kalle\Xml\Dom;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode as PhpDomNode;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Exception\DomInteropException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\Node;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;
use Kalle\Xml\Writer\NamespaceDeclarationResolver;
use ValueError;

use function get_debug_type;
use function sprintf;

final class XmlDomBridge
{
    private function __construct() {}

    public static function toDomDocument(XmlDocument $document): DOMDocument
    {
        $declaration = $document->declaration();
        $domDocument = new DOMDocument($declaration?->version() ?? '1.0');
        $namespaceResolver = new NamespaceDeclarationResolver();

        if ($declaration?->encoding() !== null) {
            $domDocument->encoding = $declaration->encoding();
        }

        if ($declaration?->standalone() !== null) {
            $domDocument->xmlStandalone = $declaration->standalone();
        }

        self::exportElement($document->root(), $domDocument, NamespaceScope::empty(), $namespaceResolver);

        return $domDocument;
    }

    public static function elementToDomDocument(Element $element): DOMDocument
    {
        $domDocument = new DOMDocument('1.0');
        $namespaceResolver = new NamespaceDeclarationResolver();
        self::exportElement($element, $domDocument, NamespaceScope::empty(), $namespaceResolver);

        return $domDocument;
    }

    private static function exportElement(
        Element $element,
        DOMDocument|DOMElement $parent,
        NamespaceScope $context,
        NamespaceDeclarationResolver $namespaceResolver,
    ): DOMElement {
        $document = $parent instanceof DOMDocument ? $parent : $parent->ownerDocument;

        if (!$document instanceof DOMDocument) {
            throw new DomInteropException(
                'XmlDomBridge requires a DOMDocument parent when exporting XML elements.',
            );
        }

        $namespaceDeclarations = $namespaceResolver->resolve(
            $element->qualifiedName(),
            self::attributesByIdentity($element->attributes()),
            self::namespaceDeclarationsByPrefix($element->namespaceDeclarations()),
            $context,
        );
        $inScopeContext = $context->withDeclarations($namespaceDeclarations);
        $domElement = self::createElementNode($document, $element->qualifiedName());
        $parent->appendChild($domElement);

        foreach ($namespaceDeclarations as $namespaceDeclaration) {
            self::writeNamespaceDeclaration($domElement, $namespaceDeclaration);
        }

        foreach ($element->attributes() as $attribute) {
            self::writeAttribute($domElement, $attribute);
        }

        foreach ($element->children() as $child) {
            if ($child instanceof Element) {
                self::exportElement($child, $domElement, $inScopeContext, $namespaceResolver);

                continue;
            }

            $domElement->appendChild(self::exportNode($child, $document));
        }

        return $domElement;
    }

    private static function exportNode(
        Node $node,
        DOMDocument $document,
    ): PhpDomNode {
        try {
            if ($node instanceof TextNode) {
                return $document->createTextNode($node->content());
            }

            if ($node instanceof CDataNode) {
                return $document->createCDATASection($node->content());
            }

            if ($node instanceof CommentNode) {
                return $document->createComment($node->content());
            }

            if ($node instanceof ProcessingInstructionNode) {
                return $document->createProcessingInstruction($node->target(), $node->data());
            }
        } catch (DOMException|ValueError $exception) {
            throw new DomInteropException(
                'XmlDomBridge cannot export an XML node into DOM: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        throw new DomInteropException(sprintf(
            'XmlDomBridge cannot export XML node type %s into DOM.',
            get_debug_type($node),
        ));
    }

    private static function createElementNode(DOMDocument $document, QualifiedName $qualifiedName): DOMElement
    {
        try {
            if ($qualifiedName->namespaceUri() === null) {
                return $document->createElement($qualifiedName->localName());
            }

            return $document->createElementNS(
                $qualifiedName->namespaceUri(),
                $qualifiedName->lexicalName(),
            );
        } catch (DOMException|ValueError $exception) {
            throw new DomInteropException(
                'XmlDomBridge cannot export an XML element into DOM: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private static function writeNamespaceDeclaration(DOMElement $element, NamespaceDeclaration $declaration): void
    {
        try {
            $element->setAttributeNS(
                QualifiedName::XMLNS_NAMESPACE_URI,
                $declaration->attributeName(),
                $declaration->uri(),
            );
        } catch (DOMException|ValueError $exception) {
            throw new DomInteropException(
                'XmlDomBridge cannot export an XML namespace declaration into DOM: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private static function writeAttribute(DOMElement $element, Attribute $attribute): void
    {
        try {
            if ($attribute->namespaceUri() === null) {
                $element->setAttribute($attribute->localName(), $attribute->value());

                return;
            }

            $element->setAttributeNS(
                $attribute->namespaceUri(),
                $attribute->name(),
                $attribute->value(),
            );
        } catch (DOMException|ValueError $exception) {
            throw new DomInteropException(
                'XmlDomBridge cannot export an XML attribute into DOM: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * @param list<Attribute> $attributes
     *
     * @return array<string, Attribute>
     */
    private static function attributesByIdentity(array $attributes): array
    {
        $indexed = [];

        foreach ($attributes as $attribute) {
            $indexed[$attribute->identityKey()] = $attribute;
        }

        return $indexed;
    }

    /**
     * @param list<NamespaceDeclaration> $namespaceDeclarations
     *
     * @return array<string, NamespaceDeclaration>
     */
    private static function namespaceDeclarationsByPrefix(array $namespaceDeclarations): array
    {
        $indexed = [];

        foreach ($namespaceDeclarations as $namespaceDeclaration) {
            $indexed[$namespaceDeclaration->prefixKey()] = $namespaceDeclaration;
        }

        return $indexed;
    }
}
