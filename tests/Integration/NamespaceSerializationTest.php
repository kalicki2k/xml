<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class NamespaceSerializationTest extends TestCase
{
    public function testItSerializesARootElementWithAnAutoDeclaredDefaultNamespace(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('catalog', 'urn:catalog'))
                ->child(Xml::element(Xml::qname('book', 'urn:catalog'))->text('Domain-Driven Design')),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>Domain-Driven Design</book></catalog>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSerializesPrefixedNamespacesWithDeterministicDeclarationOrder(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('media', 'urn:media')
                ->declareNamespace('atom', 'urn:feed')
                ->declareNamespace('xlink', 'urn:xlink')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
                        ->child(Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))),
                ),
        )->withoutDeclaration();

        $sameDocumentDifferentOrder = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->declareNamespace('atom', 'urn:feed')
                ->declareNamespace('media', 'urn:media')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
                        ->child(Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))),
                ),
        )->withoutDeclaration();

        $expected = '<atom:feed xmlns:atom="urn:feed" xmlns:media="urn:media" xmlns:xlink="urn:xlink"><atom:entry xlink:href="https://example.com/items/1"><media:thumbnail/></atom:entry></atom:feed>';

        self::assertSame($expected, $document->toString(WriterConfig::compact(emitDeclaration: false)));
        self::assertSame($expected, $sameDocumentDifferentOrder->toString(WriterConfig::compact(emitDeclaration: false)));
    }

    public function testItResetsTheDefaultNamespaceForUnqualifiedChildElements(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('catalog', 'urn:catalog'))
                ->child(Xml::element(Xml::qname('book', 'urn:catalog'))->text('DDD'))
                ->child(Xml::element('meta')->text('plain')),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>DDD</book><meta xmlns="">plain</meta></catalog>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItRebindsPrefixesCleanlyInNestedScopes(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed', 'a'))
                ->declareNamespace('a', 'urn:feed')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:entry', 'a'))
                        ->child(Xml::element(Xml::qname('title', 'urn:entry', 'a'))->text('Nested scope')),
                ),
        )->withoutDeclaration();

        self::assertSame(
            '<a:feed xmlns:a="urn:feed"><a:entry xmlns:a="urn:entry"><a:title>Nested scope</a:title></a:entry></a:feed>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSupportsNamespacedAttributesWithoutApplyingTheDefaultNamespace(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('link', 'urn:atom'))
                ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1'),
        )->withoutDeclaration();

        self::assertSame(
            '<link xmlns="urn:atom" xmlns:xlink="urn:xlink" xlink:href="https://example.com/items/1"/>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItUsesTheBuiltInXmlPrefixWithoutDeclaringItAgain(): void
    {
        $document = Xml::document(
            Xml::element('root')
                ->attribute(Xml::qname('lang', 'http://www.w3.org/XML/1998/namespace', 'xml'), 'de'),
        )->withoutDeclaration();

        self::assertSame(
            '<root xml:lang="de"/>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItCanSerializeAnExplicitXmlPrefixDeclarationWhenRequested(): void
    {
        $document = Xml::document(
            Xml::element('root')
                ->declareNamespace('xml', 'http://www.w3.org/XML/1998/namespace')
                ->attribute(Xml::qname('lang', 'http://www.w3.org/XML/1998/namespace', 'xml'), 'de'),
        )->withoutDeclaration();

        self::assertSame(
            '<root xmlns:xml="http://www.w3.org/XML/1998/namespace" xml:lang="de"/>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }
}
