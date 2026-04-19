<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function is_resource;
use function rewind;
use function stream_get_contents;

final class XmlImporterRealisticIntegrationTest extends TestCase
{
    private const FEED_NS = 'urn:feed';
    private const DC_NS = 'urn:dc';
    private const MEDIA_NS = 'urn:media';
    private const XLINK_NS = 'urn:xlink';
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testItImportsARealisticCatalogDocumentFromReaderToWriter(): void
    {
        $source = $this->createCatalogDocument();
        $readerDocument = XmlReader::fromString(XmlWriter::toString($source));

        $importedDocument = XmlImporter::document($readerDocument);

        self::assertSame(
            XmlWriter::toString($source),
            XmlWriter::toString($importedDocument),
        );
        self::assertSame(
            XmlWriter::toString($source, WriterConfig::compact()),
            XmlWriter::toString($importedDocument, WriterConfig::compact()),
        );
    }

    public function testItImportsANamespaceAwareFeedQueryResultIntoANewWriterDocument(): void
    {
        $readerDocument = XmlReader::fromString(
            XmlWriter::toString($this->createDefaultNamespaceFeedDocument(), WriterConfig::compact()),
        );
        $queryNamespaces = [
            'feed' => self::FEED_NS,
            'dc' => self::DC_NS,
            'media' => self::MEDIA_NS,
            'xlink' => self::XLINK_NS,
        ];

        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);

        self::assertNotNull($entry);

        $document = XmlBuilder::document(
            XmlBuilder::element('selection')
                ->attribute('source', 'feed')
                ->child(XmlImporter::element($entry)),
        )->withoutDeclaration();

        self::assertSame(
            '<selection source="feed"><entry xmlns="' . self::FEED_NS . '" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '" xlink:href="https://example.com/products/item-1001"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/></entry></selection>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItImportsAQueriedInvoiceSubtreeAndReserializesIt(): void
    {
        $readerDocument = XmlReader::fromString(
            XmlWriter::toString($this->createInvoiceDocument(), WriterConfig::compact()),
        );
        $queryNamespaces = [
            'inv' => self::UBL_INVOICE_NS,
            'cac' => self::UBL_CAC_NS,
            'cbc' => self::UBL_CBC_NS,
        ];

        $supplierParty = $readerDocument->findFirst(
            '/inv:Invoice/cac:AccountingSupplierParty',
            $queryNamespaces,
        );

        self::assertNotNull($supplierParty);

        $document = XmlBuilder::document(
            XmlBuilder::element('supplier-export')
                ->child(XmlImporter::element($supplierParty)),
        )->withoutDeclaration();

        self::assertSame(
            '<supplier-export><cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></supplier-export>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItStreamsImportedContentFromARealisticReaderWorkflow(): void
    {
        $readerDocument = XmlReader::fromString(
            XmlWriter::toString($this->createInvoiceDocument(), WriterConfig::compact()),
        );
        $queryNamespaces = [
            'inv' => self::UBL_INVOICE_NS,
            'cac' => self::UBL_CAC_NS,
            'cbc' => self::UBL_CBC_NS,
        ];

        $supplierParty = $readerDocument->findFirst(
            '/inv:Invoice/cac:AccountingSupplierParty',
            $queryNamespaces,
        );

        self::assertNotNull($supplierParty);

        self::assertSame(
            '<supplier-export><cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></supplier-export>',
            $this->streamToString(
                WriterConfig::compact(emitDeclaration: false),
                static function (StreamingXmlWriter $writer) use ($supplierParty): void {
                    $writer
                        ->startElement('supplier-export')
                        ->writeElement(XmlImporter::element($supplierParty))
                        ->endElement();
                },
            ),
        );
    }

    private function createCatalogDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-18T10:30:00Z')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->attribute('available', true)
                        ->child(XmlBuilder::element('title')->text('Clean Code'))
                        ->child(XmlBuilder::element('author')->text('Robert C. Martin'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('39.90')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->attribute('available', false)
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                        ->child(XmlBuilder::element('author')->text('Eric Evans'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90')),
                ),
        );
    }

    private function createDefaultNamespaceFeedDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', self::FEED_NS))
                ->declareDefaultNamespace(self::FEED_NS)
                ->declareNamespace('dc', self::DC_NS)
                ->declareNamespace('media', self::MEDIA_NS)
                ->declareNamespace('xlink', self::XLINK_NS)
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', self::FEED_NS))
                        ->attribute(XmlBuilder::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1001')
                        ->child(XmlBuilder::element(XmlBuilder::qname('title', self::FEED_NS))->text('Blue mug'))
                        ->child(XmlBuilder::element(XmlBuilder::qname('identifier', self::DC_NS, 'dc'))->text('item-1001'))
                        ->child(
                            XmlBuilder::element(XmlBuilder::qname('thumbnail', self::MEDIA_NS, 'media'))
                                ->attribute(XmlBuilder::qname('href', self::XLINK_NS, 'xlink'), 'https://cdn.example.com/products/item-1001.jpg')
                                ->attribute('width', 320)
                                ->attribute('height', 180),
                        ),
                )
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', self::FEED_NS))
                        ->attribute(XmlBuilder::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1002')
                        ->child(XmlBuilder::element(XmlBuilder::qname('title', self::FEED_NS))->text('Notebook set'))
                        ->child(XmlBuilder::element(XmlBuilder::qname('identifier', self::DC_NS, 'dc'))->text('item-1002')),
                ),
        )->withoutDeclaration();
    }

    private function createInvoiceDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareDefaultNamespace(self::UBL_INVOICE_NS)
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->attribute(XmlBuilder::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml'), 'de')
                ->attribute(
                    XmlBuilder::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
                )
                ->child(XmlBuilder::element(XmlBuilder::qname('ID', self::UBL_CBC_NS, 'cbc'))->text('RE-2026-0042'))
                ->child(XmlBuilder::element(XmlBuilder::qname('IssueDate', self::UBL_CBC_NS, 'cbc'))->text('2026-04-17'))
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))
                        ->child(
                            XmlBuilder::element(XmlBuilder::qname('Party', self::UBL_CAC_NS, 'cac'))
                                ->child(
                                    XmlBuilder::element(XmlBuilder::qname('EndpointID', self::UBL_CBC_NS, 'cbc'))
                                        ->attribute('schemeID', '0088')
                                        ->text('0409876543210'),
                                )
                                ->child(
                                    XmlBuilder::element(XmlBuilder::qname('PartyName', self::UBL_CAC_NS, 'cac'))
                                        ->child(XmlBuilder::element(XmlBuilder::qname('Name', self::UBL_CBC_NS, 'cbc'))->text('Muster Software GmbH')),
                                ),
                        ),
                ),
        )->withoutDeclaration();
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
