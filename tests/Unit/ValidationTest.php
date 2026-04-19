<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\DuplicateAttributeException;
use Kalle\Xml\Exception\DuplicateNamespaceDeclarationException;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Exception\InvalidWriterConfigException;
use Kalle\Xml\Exception\InvalidXmlCharacter;
use Kalle\Xml\Exception\InvalidXmlContent;
use Kalle\Xml\Exception\InvalidXmlDeclarationException;
use Kalle\Xml\Exception\InvalidXmlName;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Name\XmlNameValidator;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ValidationTest extends TestCase
{
    public function testItRejectsRawPrefixedElementNamesInFavorOfQualifiedNames(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('XmlBuilder::qname()');

        XmlBuilder::element('ns:book');
    }

    public function testItRejectsRawPrefixedAttributeNamesEvenWhenRemovingAttributes(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('XmlBuilder::qname()');

        XmlBuilder::element('book')->attribute('xml:lang', null);
    }

    public function testItRejectsRawPrefixedAttributeNamesWhenAddingAttributes(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('XmlBuilder::qname()');

        XmlBuilder::element('book')->attribute('xml:lang', 'de');
    }

    public function testItRejectsNamespacedAttributesWithoutAnExplicitPrefix(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('Default namespaces do not apply to attributes');

        XmlBuilder::element('book')->attribute(XmlBuilder::qname('href', 'urn:link'), 'https://example.com');
    }

    public function testItAllowsAstralPlaneCharactersInXmlNames(): void
    {
        $elementName = "𐐀catalog";
        $attributeName = XmlBuilder::qname("𐐀href", 'urn:link', 'link');

        $element = XmlBuilder::element($elementName)->attribute($attributeName, 'https://example.com');

        self::assertSame($elementName, $element->name());
        self::assertSame('link:𐐀href', $element->attributes()[0]->name());
    }

    public function testItRejectsConflictingNamespaceDeclarationsOnTheSameElement(): void
    {
        $this->expectException(DuplicateNamespaceDeclarationException::class);
        $this->expectExceptionMessage('already declares the default namespace');

        XmlBuilder::element(XmlBuilder::qname('catalog', 'urn:catalog'))
            ->declareDefaultNamespace('urn:catalog')
            ->declareDefaultNamespace('urn:other');
    }

    public function testItRejectsNonEmptyDefaultNamespaceOnAnUnqualifiedElement(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('Unqualified element');

        XmlBuilder::element('catalog')->declareDefaultNamespace('urn:catalog');
    }

    public function testItRejectsEmptyNamespacePrefixesInTheFluentApi(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('declareDefaultNamespace()');

        XmlBuilder::element('catalog')->declareNamespace('', 'urn:catalog');
    }

    public function testItRejectsEmptyNamespacePrefixesInNamespaceDeclarations(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('Use null for the default namespace');

        new NamespaceDeclaration('', 'urn:catalog');
    }

    public function testItRejectsUndeclaringPrefixedNamespacesInXml10(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('cannot be undeclared in XML 1.0');

        new NamespaceDeclaration('link', '');
    }

    public function testItAllowsDeclaringTheReservedXmlPrefixWithItsFixedNamespaceUri(): void
    {
        $element = XmlBuilder::element('root')->declareNamespace('xml', QualifiedName::XML_NAMESPACE_URI);

        self::assertCount(1, $element->namespaceDeclarations());
        self::assertSame('xml', $element->namespaceDeclarations()[0]->prefix());
        self::assertSame(QualifiedName::XML_NAMESPACE_URI, $element->namespaceDeclarations()[0]->uri());
    }

    public function testItRejectsBindingAnotherPrefixToTheXmlNamespaceUri(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('can only be bound to prefix "xml"');

        XmlBuilder::element('root')->declareNamespace('lang', QualifiedName::XML_NAMESPACE_URI);
    }

    public function testItExplainsWhichContextsReuseTheSamePrefixForDifferentUris(): void
    {
        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('element "a:entry"');
        $this->expectExceptionMessage('attribute "a:href"');

        XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'a'))
            ->attribute(XmlBuilder::qname('href', 'urn:link', 'a'), 'https://example.com/items/1');
    }

    public function testItRejectsInvalidXmlCharactersInTextNodes(): void
    {
        $this->expectException(InvalidXmlCharacter::class);
        $this->expectExceptionMessage('Text node content contains invalid XML character U+0001');

        XmlBuilder::text("bad\u{0001}value");
    }

    public function testItRejectsInvalidCommentContent(): void
    {
        $this->expectException(InvalidXmlContent::class);
        $this->expectExceptionMessage('cannot contain "--"');

        XmlBuilder::comment('bad -- comment');
    }

    public function testItRejectsCommentContentEndingWithAHyphen(): void
    {
        $this->expectException(InvalidXmlContent::class);
        $this->expectExceptionMessage('cannot end with "-"');

        XmlBuilder::comment('bad-');
    }

    public function testItRejectsInvalidProcessingInstructionData(): void
    {
        $this->expectException(InvalidXmlContent::class);
        $this->expectExceptionMessage('Processing instruction data for "xml-stylesheet" cannot contain "?>"');

        XmlBuilder::processingInstruction('xml-stylesheet', 'href="catalog.xsl"?>');
    }

    public function testItRejectsReservedProcessingInstructionTargets(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('reserved');

        XmlBuilder::processingInstruction('xml');
    }

    public function testItRejectsProcessingInstructionTargetsContainingColons(): void
    {
        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('cannot contain ":"');

        XmlBuilder::processingInstruction('xml:stylesheet');
    }

    public function testItRejectsDuplicateAttributesInElementConstruction(): void
    {
        $this->expectException(DuplicateAttributeException::class);
        $this->expectExceptionMessage('already has attribute "isbn"');

        new Element('book', [
            new Attribute('isbn', '9780132350884'),
            new Attribute('isbn', '9780132350885'),
        ]);
    }

    public function testItRejectsUnsupportedXmlDeclarationEncodings(): void
    {
        $this->expectException(InvalidXmlDeclarationException::class);
        $this->expectExceptionMessage('Encoding must be UTF-8');

        new XmlDeclaration(encoding: 'ISO-8859-1');
    }

    public function testItRejectsInvalidWriterIndentation(): void
    {
        $this->expectException(InvalidWriterConfigException::class);
        $this->expectExceptionMessage('spaces or tabs');

        WriterConfig::pretty('..');
    }

    public function testStaticUtilityClassesAreNotInstantiable(): void
    {
        self::assertFalse((new ReflectionClass(XmlBuilder::class))->isInstantiable());
        self::assertFalse((new ReflectionClass(XmlNameValidator::class))->isInstantiable());
        self::assertFalse((new ReflectionClass(XmlEscaper::class))->isInstantiable());
    }
}
