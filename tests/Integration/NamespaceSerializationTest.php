<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

final class NamespaceSerializationTest extends TestCase
{
    public function testItSerializesARootElementWithAnAutoDeclaredDefaultNamespace(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('catalog', 'urn:catalog'))
                ->child(XmlBuilder::element(XmlBuilder::qname('book', 'urn:catalog'))->text('Domain-Driven Design')),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>Domain-Driven Design</book></catalog>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSerializesPrefixedNamespacesWithDeterministicDeclarationOrder(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('media', 'urn:media')
                ->declareNamespace('atom', 'urn:feed')
                ->declareNamespace('xlink', 'urn:xlink')
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
                        ->child(XmlBuilder::element(XmlBuilder::qname('thumbnail', 'urn:media', 'media'))),
                ),
        )->withoutDeclaration();

        $sameDocumentDifferentOrder = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->declareNamespace('atom', 'urn:feed')
                ->declareNamespace('media', 'urn:media')
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
                        ->child(XmlBuilder::element(XmlBuilder::qname('thumbnail', 'urn:media', 'media'))),
                ),
        )->withoutDeclaration();

        $expected = '<atom:feed xmlns:atom="urn:feed" xmlns:media="urn:media" xmlns:xlink="urn:xlink"><atom:entry xlink:href="https://example.com/items/1"><media:thumbnail/></atom:entry></atom:feed>';

        self::assertSame($expected, XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)));
        self::assertSame($expected, XmlWriter::toString($sameDocumentDifferentOrder, WriterConfig::compact(emitDeclaration: false)));
    }

    public function testItResetsTheDefaultNamespaceForUnqualifiedChildElements(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('catalog', 'urn:catalog'))
                ->child(XmlBuilder::element(XmlBuilder::qname('book', 'urn:catalog'))->text('DDD'))
                ->child(XmlBuilder::element('meta')->text('plain')),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>DDD</book><meta xmlns="">plain</meta></catalog>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItRebindsPrefixesCleanlyInNestedScopes(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'a'))
                ->declareNamespace('a', 'urn:feed')
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:entry', 'a'))
                        ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:entry', 'a'))->text('Nested scope')),
                ),
        )->withoutDeclaration();

        self::assertSame(
            '<a:feed xmlns:a="urn:feed"><a:entry xmlns:a="urn:entry"><a:title>Nested scope</a:title></a:entry></a:feed>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSupportsNamespacedAttributesWithoutApplyingTheDefaultNamespace(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('link', 'urn:atom'))
                ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1'),
        )->withoutDeclaration();

        self::assertSame(
            '<link xmlns="urn:atom" xmlns:xlink="urn:xlink" xlink:href="https://example.com/items/1"/>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItUsesTheBuiltInXmlPrefixWithoutDeclaringItAgain(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('root')
                ->attribute(XmlBuilder::qname('lang', 'http://www.w3.org/XML/1998/namespace', 'xml'), 'de'),
        )->withoutDeclaration();

        self::assertSame(
            '<root xml:lang="de"/>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItCanSerializeAnExplicitXmlPrefixDeclarationWhenRequested(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('root')
                ->declareNamespace('xml', 'http://www.w3.org/XML/1998/namespace')
                ->attribute(XmlBuilder::qname('lang', 'http://www.w3.org/XML/1998/namespace', 'xml'), 'de'),
        )->withoutDeclaration();

        self::assertSame(
            '<root xmlns:xml="http://www.w3.org/XML/1998/namespace" xml:lang="de"/>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }
}
