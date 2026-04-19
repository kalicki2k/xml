<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\StreamingXmlReader;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function is_resource;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class StreamingXmlReaderRealisticIntegrationTest extends TestCase
{
    private const FEED_NS = 'urn:feed';
    private const DC_NS = 'urn:dc';
    private const MEDIA_NS = 'urn:media';
    private const XLINK_NS = 'urn:xlink';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function testItStreamsACatalogFixtureFromFileAndExpandsSelectedBooks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-stream-realistic-catalog-');

        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $this->catalogFixture()));

        try {
            $reader = StreamingXmlReader::fromFile($path);
            $selectedBooks = [];

            while ($reader->read()) {
                if (!$reader->isStartElement('book')) {
                    continue;
                }

                if ($reader->attributeValue('available') !== 'true') {
                    continue;
                }

                $book = $reader->expandElement();

                $selectedBooks[] = [
                    'isbn' => $reader->attributeValue('isbn'),
                    'title' => $book->firstChildElement('title')?->text(),
                    'price' => $book->firstChildElement('price')?->text(),
                    'currency' => $book->firstChildElement('price')?->attributeValue('currency'),
                ];
            }

            self::assertSame([
                [
                    'isbn' => '9780132350884',
                    'title' => 'Clean Code',
                    'price' => '39.90',
                    'currency' => 'EUR',
                ],
                [
                    'isbn' => '9781491950357',
                    'title' => 'Designing Data-Intensive Applications',
                    'price' => '49.90',
                    'currency' => 'EUR',
                ],
            ], $selectedBooks);
        } finally {
            @unlink($path);
        }
    }

    public function testItStreamsANamespaceAwareFeedFixtureFromStreamAndExtractsMatchingEntries(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, $this->feedFixture()));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $matchedEntries = [];

            while ($reader->read()) {
                if (!$reader->isStartElement(XmlBuilder::qname('entry', self::FEED_NS))) {
                    continue;
                }

                if ($reader->attributeValue(XmlBuilder::qname('href', self::XLINK_NS, 'xlink')) === null) {
                    continue;
                }

                $entryXml = $reader->extractElementXml();
                $entry = XmlReader::fromString($entryXml)->rootElement();
                $thumbnail = $entry->findFirst('./media:thumbnail', [
                    'media' => self::MEDIA_NS,
                ]);

                $matchedEntries[] = [
                    'sku' => $entry->attributeValue('sku'),
                    'href' => $entry->attributeValue(XmlBuilder::qname('href', self::XLINK_NS, 'xlink')),
                    'title' => $entry->findFirst('./feed:title', ['feed' => self::FEED_NS])?->text(),
                    'identifier' => $entry->findFirst('./dc:identifier', ['dc' => self::DC_NS])?->text(),
                    'thumbnailHref' => $thumbnail?->attributeValue(XmlBuilder::qname('href', self::XLINK_NS, 'xlink')),
                ];
            }

            self::assertSame([
                [
                    'sku' => 'item-1001',
                    'href' => 'https://example.com/products/item-1001',
                    'title' => 'Blue mug',
                    'identifier' => 'item-1001',
                    'thumbnailHref' => 'https://cdn.example.com/products/item-1001.jpg',
                ],
                [
                    'sku' => 'item-1002',
                    'href' => 'https://example.com/products/item-1002',
                    'title' => 'Notebook set',
                    'identifier' => 'item-1002',
                    'thumbnailHref' => 'https://cdn.example.com/products/item-1002.jpg',
                ],
                [
                    'sku' => 'item-1003',
                    'href' => 'https://example.com/products/item-1003',
                    'title' => 'Desk lamp',
                    'identifier' => 'item-1003',
                    'thumbnailHref' => 'https://cdn.example.com/products/item-1003.jpg',
                ],
            ], $matchedEntries);
        } finally {
            fclose($stream);
        }
    }

    public function testItImportsInvoiceSubtreesSelectedFromStreamingReader(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, $this->invoiceFixture()));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $supplierExport = null;
            $lineExport = null;

            while ($reader->read()) {
                if ($reader->isStartElement(XmlBuilder::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))) {
                    $supplierExport = XmlWriter::toString(
                        XmlBuilder::document(
                            XmlBuilder::element('supplier-export')
                                ->child(XmlImporter::element($reader->expandElement())),
                        )->withoutDeclaration(),
                        WriterConfig::compact(emitDeclaration: false),
                    );

                    continue;
                }

                if (!$reader->isStartElement(XmlBuilder::qname('InvoiceLine', self::UBL_CAC_NS, 'cac'))) {
                    continue;
                }

                $invoiceLine = XmlReader::fromString($reader->extractElementXml())->rootElement();

                if ($invoiceLine->findFirst('./cbc:ID', ['cbc' => self::UBL_CBC_NS])?->text() !== '2') {
                    continue;
                }

                $lineExport = XmlWriter::toString(
                    XmlBuilder::document(
                        XmlBuilder::element('line-export')
                            ->child(XmlImporter::element($invoiceLine)),
                    )->withoutDeclaration(),
                    WriterConfig::compact(emitDeclaration: false),
                );
            }

            self::assertSame(
                '<supplier-export><cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></supplier-export>',
                $supplierExport,
            );
            self::assertSame(
                '<line-export><cac:InvoiceLine xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cbc:ID>2</cbc:ID><cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity><cbc:LineExtensionAmount currencyID="EUR">24.00</cbc:LineExtensionAmount><cac:Item><cbc:Name>Notebook set</cbc:Name></cac:Item></cac:InvoiceLine></line-export>',
                $lineExport,
            );
        } finally {
            fclose($stream);
        }
    }

    public function testItStreamsFilteredEntriesBackOutThroughStreamingXmlWriter(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, $this->feedFixture()));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            self::assertSame(
                '<selection><entry xmlns="urn:feed" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '" sku="item-1001" xlink:href="https://example.com/products/item-1001" selected="true"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/></entry><entry xmlns="urn:feed" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '" sku="item-1003" xlink:href="https://example.com/products/item-1003" selected="true"><title>Desk lamp</title><dc:identifier>item-1003</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1003.jpg" width="320" height="180"/></entry></selection>',
                $this->streamToString(
                    WriterConfig::compact(emitDeclaration: false),
                    static function (StreamingXmlWriter $writer) use ($reader): void {
                        $writer->startElement('selection');

                        while ($reader->read()) {
                            if (!$reader->isStartElement(XmlBuilder::qname('entry', self::FEED_NS))) {
                                continue;
                            }

                            if ($reader->attributeValue('sku') === 'item-1002') {
                                continue;
                            }

                            $writer->writeElement(
                                XmlImporter::element($reader->expandElement())->attribute('selected', true),
                            );
                        }

                        $writer->endElement();
                    },
                ),
            );
        } finally {
            fclose($stream);
        }
    }

    private function catalogFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalog generatedAt="2026-04-18T10:30:00Z">
    <book isbn="9780132350884" available="true">
        <title>Clean Code</title>
        <author>Robert C. Martin</author>
        <price currency="EUR">39.90</price>
    </book>
    <book isbn="9780321125217" available="false">
        <title>Domain-Driven Design</title>
        <author>Eric Evans</author>
        <price currency="EUR">54.90</price>
    </book>
    <book isbn="9781491950357" available="true">
        <title>Designing Data-Intensive Applications</title>
        <author>Martin Kleppmann</author>
        <price currency="EUR">49.90</price>
    </book>
</catalog>
XML;
    }

    private function feedFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:media="urn:media" xmlns:xlink="urn:xlink">
    <entry sku="item-1001" xlink:href="https://example.com/products/item-1001">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/>
    </entry>
    <entry sku="item-1002" xlink:href="https://example.com/products/item-1002">
        <title>Notebook set</title>
        <dc:identifier>item-1002</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1002.jpg" width="320" height="180"/>
    </entry>
    <entry sku="item-1003" xlink:href="https://example.com/products/item-1003">
        <title>Desk lamp</title>
        <dc:identifier>item-1003</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1003.jpg" width="320" height="180"/>
    </entry>
</feed>
XML;
    }

    private function invoiceFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cbc:IssueDate>2026-04-18</cbc:IssueDate>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID>
            <cac:PartyName>
                <cbc:Name>Muster Software GmbH</cbc:Name>
            </cac:PartyName>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">12.00</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>Blue mug</cbc:Name>
        </cac:Item>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
        <cbc:ID>2</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">24.00</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>Notebook set</cbc:Name>
        </cac:Item>
    </cac:InvoiceLine>
</Invoice>
XML;
    }

    /**
     * @param callable(StreamingXmlWriter): void $write
     */
    private function streamToString(WriterConfig $config, callable $write): string
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        try {
            $writer = StreamingXmlWriter::forStream($stream, $config);
            $write($writer);
            $writer->finish();
            rewind($stream);

            return (string) stream_get_contents($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
