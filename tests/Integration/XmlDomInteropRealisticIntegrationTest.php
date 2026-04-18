<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use DOMDocument;
use DOMElement;
use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class XmlDomInteropRealisticIntegrationTest extends TestCase
{
    private const FEED_NS = 'urn:feed';
    private const DC_NS = 'urn:dc';
    private const MEDIA_NS = 'urn:media';
    private const XLINK_NS = 'urn:xlink';
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testItLoadsARealisticCatalogFixtureAfterWriterDomReaderRoundtrip(): void
    {
        $readerDocument = XmlReader::fromDomDocument(
            XmlDomBridge::toDomDocument($this->createCatalogDocument()),
        );

        $root = $readerDocument->rootElement();
        $books = $root->childElements('book');
        $firstBook = $root->firstChildElement('book');
        $secondBook = $books[1] ?? null;

        self::assertSame('catalog', $root->name());
        self::assertSame('2026-04-17T10:30:00Z', $root->attributeValue('generatedAt'));
        self::assertCount(2, $books);
        self::assertNotNull($firstBook);
        self::assertNotNull($secondBook);
        self::assertSame('9780132350884', $firstBook->attributeValue('isbn'));
        self::assertSame('true', $firstBook->attributeValue('available'));
        self::assertSame('Clean Code', $firstBook->firstChildElement('title')?->text());
        self::assertSame('Robert C. Martin', $firstBook->firstChildElement('author')?->text());
        self::assertSame('39.90', $firstBook->firstChildElement('price')?->text());
        self::assertSame('54.90', $secondBook->firstChildElement('price')?->text());
    }

    public function testItQueriesARealisticNamespaceAwareFeedFixtureAfterWriterDomReaderRoundtrip(): void
    {
        $readerDocument = XmlReader::fromDomDocument(
            XmlDomBridge::toDomDocument($this->createFeedDocument()),
        );
        $queryNamespaces = [
            'feed' => self::FEED_NS,
            'dc' => self::DC_NS,
            'media' => self::MEDIA_NS,
            'xlink' => self::XLINK_NS,
        ];

        $entries = $readerDocument->findAll('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);
        $firstEntry = $entries[0] ?? null;
        $thumbnail = $firstEntry?->findFirst('./media:thumbnail[@width="320"]', $queryNamespaces);

        self::assertSame(self::FEED_NS, $readerDocument->rootElement()->namespaceUri());
        self::assertCount(2, $entries);
        self::assertNotNull($firstEntry);
        self::assertSame('Blue mug', $firstEntry->findFirst('./feed:title', $queryNamespaces)?->text());
        self::assertSame('item-1001', $firstEntry->findFirst('./dc:identifier', $queryNamespaces)?->text());
        self::assertSame(
            'https://example.com/products/item-1001',
            $firstEntry->attributeValue(Xml::qname('href', self::XLINK_NS, 'xlink')),
        );
        self::assertNotNull($thumbnail);
        self::assertSame('180', $thumbnail->attributeValue('height'));
        self::assertSame(
            'https://cdn.example.com/products/item-1001.jpg',
            $thumbnail->attributeValue(Xml::qname('href', self::XLINK_NS, 'xlink')),
        );
    }

    public function testItImportsARealisticInvoiceFixtureLoadedFromDomAndReserializesIt(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        self::assertTrue($domDocument->loadXML($this->invoiceXmlFixture()));

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $root = $readerDocument->rootElement();
        $importedDocument = XmlImporter::document($readerDocument)->withoutDeclaration();

        self::assertSame('Invoice', $root->name());
        self::assertSame(
            'RE-2026-0042',
            $root->firstChildElement(Xml::qname('ID', self::UBL_CBC_NS, 'cbc'))?->text(),
        );
        self::assertSame(
            'de',
            $root->attributeValue(Xml::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml')),
        );
        self::assertSame(
            '<Invoice xmlns="' . self::UBL_INVOICE_NS . '" xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '" xmlns:xsi="' . self::XSI_NS . '" xml:lang="de" xsi:schemaLocation="' . self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd"><cbc:ID>RE-2026-0042</cbc:ID><cbc:IssueDate>2026-04-17</cbc:IssueDate><cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></Invoice>',
            $importedDocument->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItStreamsDomBasedFeedContentThroughReaderImportAndStreamingWriter(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        self::assertTrue($domDocument->loadXML(
            '<feed xmlns="' . self::FEED_NS . '" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '"><entry xlink:href="https://example.com/products/item-1001"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/></entry></feed>',
        ));

        $entry = $domDocument->getElementsByTagNameNS(self::FEED_NS, 'entry')->item(0);

        self::assertInstanceOf(DOMElement::class, $entry);

        $readerElement = XmlReader::fromDomElement($entry);
        $writer = StreamingXmlWriter::forString(
            WriterConfig::compact(emitDeclaration: false),
        );

        $writer
            ->startElement('export')
            ->writeElement(XmlImporter::element($readerElement))
            ->endElement()
            ->finish();

        self::assertSame(
            '<export><entry xmlns="' . self::FEED_NS . '" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '" xlink:href="https://example.com/products/item-1001"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/></entry></export>',
            $writer->toString(),
        );
    }

    private function createCatalogDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->attribute('available', true)
                        ->child(Xml::element('title')->text('Clean Code'))
                        ->child(Xml::element('author')->text('Robert C. Martin'))
                        ->child(Xml::element('price')->attribute('currency', 'EUR')->text('39.90')),
                )
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->attribute('available', false)
                        ->child(Xml::element('title')->text('Domain-Driven Design'))
                        ->child(Xml::element('author')->text('Eric Evans'))
                        ->child(Xml::element('price')->attribute('currency', 'EUR')->text('54.90')),
                ),
        );
    }

    private function createFeedDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element(Xml::qname('feed', self::FEED_NS))
                ->declareDefaultNamespace(self::FEED_NS)
                ->declareNamespace('dc', self::DC_NS)
                ->declareNamespace('media', self::MEDIA_NS)
                ->declareNamespace('xlink', self::XLINK_NS)
                ->child(
                    Xml::element(Xml::qname('entry', self::FEED_NS))
                        ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1001')
                        ->child(Xml::element(Xml::qname('title', self::FEED_NS))->text('Blue mug'))
                        ->child(Xml::element(Xml::qname('identifier', self::DC_NS, 'dc'))->text('item-1001'))
                        ->child(
                            Xml::element(Xml::qname('thumbnail', self::MEDIA_NS, 'media'))
                                ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://cdn.example.com/products/item-1001.jpg')
                                ->attribute('width', 320)
                                ->attribute('height', 180),
                        ),
                )
                ->child(
                    Xml::element(Xml::qname('entry', self::FEED_NS))
                        ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1002')
                        ->child(Xml::element(Xml::qname('title', self::FEED_NS))->text('Notebook set'))
                        ->child(Xml::element(Xml::qname('identifier', self::DC_NS, 'dc'))->text('item-1002')),
                ),
        )->withoutDeclaration();
    }

    private function invoiceXmlFixture(): string
    {
        return '<Invoice xmlns="' . self::UBL_INVOICE_NS . '" xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '" xmlns:xsi="' . self::XSI_NS . '" xml:lang="de" xsi:schemaLocation="' . self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd"><cbc:ID>RE-2026-0042</cbc:ID><cbc:IssueDate>2026-04-17</cbc:IssueDate><cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></Invoice>';
    }
}
