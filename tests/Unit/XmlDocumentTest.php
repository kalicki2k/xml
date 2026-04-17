<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class XmlDocumentTest extends TestCase
{
    public function testItCreatesASimpleDocumentFromTheBuilder(): void
    {
        $document = Xml::document(Xml::element('catalog'));

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame('catalog', $document->root()->name());
        self::assertInstanceOf(XmlDeclaration::class, $document->declaration());
        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><catalog/>',
            $document->toString(),
        );
    }

    public function testWithDeclarationReturnsANewDocumentInstance(): void
    {
        $document = Xml::document(Xml::element('catalog'));
        $updated = $document->withDeclaration(new XmlDeclaration(standalone: true));

        self::assertNotSame($document, $updated);
        self::assertNull($document->declaration()?->standalone());
        self::assertTrue($updated->declaration()?->standalone());
    }

    public function testWithRootReturnsANewDocumentWithTheSameDeclaration(): void
    {
        $document = Xml::document(Xml::element('catalog'))
            ->withDeclaration(new XmlDeclaration(standalone: true));

        $updated = $document->withRoot(Xml::element('library'));

        self::assertNotSame($document, $updated);
        self::assertSame('catalog', $document->root()->name());
        self::assertSame('library', $updated->root()->name());
        self::assertTrue($updated->declaration()?->standalone());
    }

    public function testNoOpDocumentFluentMethodsReturnTheSameInstance(): void
    {
        $declaration = new XmlDeclaration();
        $root = Xml::element('catalog');
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

    public function testPrettyPrintingUsesTheWriterConfig(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(Xml::element('book'))
                ->child(Xml::element('magazine')),
        );

        $xml = $document->toString(WriterConfig::pretty(emitDeclaration: false));

        self::assertSame(
            "<catalog>\n    <book/>\n    <magazine/>\n</catalog>",
            $xml,
        );
    }

    public function testWithoutDeclarationRemovesTheDocumentDeclaration(): void
    {
        $document = Xml::document(Xml::element('catalog'))->withoutDeclaration();

        self::assertNull($document->declaration());
        self::assertSame('<catalog/>', $document->toString());
    }

    public function testBuilderCanCreateDeclarationsConveniently(): void
    {
        $document = Xml::document(Xml::element('catalog'))
            ->withDeclaration(Xml::declaration(standalone: true));

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><catalog/>',
            $document->toString(WriterConfig::compact()),
        );
    }

    public function testWriterConfigCanDisableSelfClosingEmptyElements(): void
    {
        $document = Xml::document(Xml::element('catalog'));

        self::assertSame(
            '<catalog></catalog>',
            $document->withoutDeclaration()->toString(WriterConfig::compact(
                emitDeclaration: false,
                selfCloseEmptyElements: false,
            )),
        );
    }
}
