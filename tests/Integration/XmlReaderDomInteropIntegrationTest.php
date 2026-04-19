<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use DOMDocument;
use DOMElement;
use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

final class XmlReaderDomInteropIntegrationTest extends TestCase
{
    public function testItLoadsASimpleDomDocumentIntoReaderDocument(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $catalog = $domDocument->createElement('catalog');
        $catalog->setAttribute('generatedAt', '2026-04-17T10:30:00Z');

        $book = $domDocument->createElement('book');
        $book->setAttribute('isbn', '9780132350884');

        $title = $domDocument->createElement('title');
        $title->appendChild($domDocument->createTextNode('Clean Code'));

        $book->appendChild($title);
        $catalog->appendChild($book);
        $domDocument->appendChild($catalog);

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $root = $readerDocument->rootElement();
        $firstBook = $root->firstChildElement('book');

        self::assertSame('catalog', $root->name());
        self::assertSame('2026-04-17T10:30:00Z', $root->attributeValue('generatedAt'));
        self::assertCount(1, $root->childElements());
        self::assertNotNull($firstBook);
        self::assertSame('9780132350884', $firstBook->attributeValue('isbn'));
        self::assertSame('Clean Code', $firstBook->firstChildElement('title')?->text());
    }

    public function testItLoadsANamespacedDomDocumentIntoReaderDocument(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        self::assertTrue($domDocument->loadXML(
            '<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink"><entry xlink:href="https://example.com/items/1"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier></entry></feed>',
        ));

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $root = $readerDocument->rootElement();
        $entry = $root->firstChildElement(XmlBuilder::qname('entry', 'urn:feed'));

        self::assertSame('feed', $root->name());
        self::assertSame('urn:feed', $root->namespaceUri());
        self::assertCount(3, $root->namespacesInScope());
        self::assertNotNull($entry);
        self::assertSame(
            'https://example.com/items/1',
            $entry->attributeValue(XmlBuilder::qname('href', 'urn:xlink', 'xlink')),
        );
        self::assertSame(
            'Blue mug',
            $entry->firstChildElement(XmlBuilder::qname('title', 'urn:feed'))?->text(),
        );
        self::assertSame(
            'item-1001',
            $entry->firstChildElement(XmlBuilder::qname('identifier', 'urn:dc', 'dc'))?->text(),
        );
    }

    public function testItLoadsADomElementIntoReaderElement(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        self::assertTrue($domDocument->loadXML(
            '<feed xmlns="urn:feed"><entry sku="item-1002"><title>Notebook set</title></entry></feed>',
        ));

        $entry = $domDocument->documentElement?->firstChild;

        self::assertInstanceOf(DOMElement::class, $entry);

        $readerElement = XmlReader::fromDomElement($entry);

        self::assertSame('entry', $readerElement->name());
        self::assertSame('urn:feed', $readerElement->namespaceUri());
        self::assertSame('item-1002', $readerElement->attributeValue('sku'));
        self::assertCount(1, $readerElement->childElements());
        self::assertSame(
            'Notebook set',
            $readerElement->firstChildElement(XmlBuilder::qname('title', 'urn:feed'))?->text(),
        );
        self::assertSame('feed', $readerElement->parent()?->name());
    }

    public function testItSupportsDomDocumentReaderQueryImportDocumentWorkflows(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        self::assertTrue($domDocument->loadXML(
            '<feed xmlns="urn:feed" xmlns:xlink="urn:xlink"><entry sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title></entry></feed>',
        ));

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@xlink:href]', [
            'feed' => 'urn:feed',
            'xlink' => 'urn:xlink',
        ]);

        self::assertNotNull($entry);

        $document = XmlBuilder::document(
            XmlImporter::element($entry)->attribute('exported', true),
        )->withoutDeclaration();

        self::assertSame(
            '<entry xmlns="urn:feed" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2" exported="true"><title>Notebook set</title></entry>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }
}
