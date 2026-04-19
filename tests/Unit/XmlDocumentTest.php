<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class XmlDocumentTest extends TestCase
{
    public function testItCreatesASimpleDocumentFromTheBuilder(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'));

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame('catalog', $document->root()->name());
        self::assertInstanceOf(XmlDeclaration::class, $document->declaration());
    }

    public function testWithDeclarationReturnsANewDocumentInstance(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'));
        $updated = $document->withDeclaration(new XmlDeclaration(standalone: true));

        self::assertNotSame($document, $updated);
        self::assertNull($document->declaration()?->standalone());
        self::assertTrue($updated->declaration()?->standalone());
    }

    public function testWithRootReturnsANewDocumentWithTheSameDeclaration(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'))
            ->withDeclaration(new XmlDeclaration(standalone: true));

        $updated = $document->withRoot(XmlBuilder::element('library'));

        self::assertNotSame($document, $updated);
        self::assertSame('catalog', $document->root()->name());
        self::assertSame('library', $updated->root()->name());
        self::assertTrue($updated->declaration()?->standalone());
    }

    public function testNoOpDocumentFluentMethodsReturnTheSameInstance(): void
    {
        $declaration = new XmlDeclaration();
        $root = XmlBuilder::element('catalog');
        $document = new XmlDocument($root, $declaration);
        $documentWithoutDeclaration = new XmlDocument($root);

        self::assertSame($document, $document->withRoot($root));
        self::assertSame($document, $document->withDeclaration($declaration));
        self::assertSame($documentWithoutDeclaration, $documentWithoutDeclaration->withoutDeclaration());
    }

    public function testNoOpXmlDeclarationWithersReturnTheSameInstance(): void
    {
        $declaration = new XmlDeclaration(standalone: true);

        self::assertSame($declaration, $declaration->withVersion('1.0'));
        self::assertSame($declaration, $declaration->withEncoding('utf-8'));
        self::assertSame($declaration, $declaration->withStandalone(true));
    }

    public function testWithoutDeclarationRemovesTheDocumentDeclaration(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'))->withoutDeclaration();

        self::assertNull($document->declaration());
    }

    public function testBuilderCanCreateDeclarationsConveniently(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'))
            ->withDeclaration(XmlBuilder::declaration(standalone: true));

        self::assertTrue($document->declaration()?->standalone() ?? false);
    }

    public function testXmlDocumentDoesNotExposeSerializationMethods(): void
    {
        $publicMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(XmlDocument::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertNotContains('toString', $publicMethods);
        self::assertNotContains('saveToFile', $publicMethods);
        self::assertNotContains('saveToStream', $publicMethods);
    }
}
