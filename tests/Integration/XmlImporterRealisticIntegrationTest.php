<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

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
        $readerDocument = XmlReader::fromString($source->toString());

        $importedDocument = XmlImporter::document($readerDocument);

        self::assertSame(
            $source->toString(),
            $importedDocument->toString(),
        );
        self::assertSame(
            $source->toString(WriterConfig::compact()),
            $importedDocument->toString(WriterConfig::compact()),
        );
    }

    public function testItImportsANamespaceAwareFeedQueryResultIntoANewWriterDocument(): void
    {
        $readerDocument = XmlReader::fromString(
            $this->createDefaultNamespaceFeedDocument()->toString(WriterConfig::compact()),
        );
        $queryNamespaces = [
            'feed' => self::FEED_NS,
            'dc' => self::DC_NS,
            'media' => self::MEDIA_NS,
            'xlink' => self::XLINK_NS,
        ];

        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);

        self::assertNotNull($entry);

        $document = Xml::document(
            Xml::element('selection')
                ->attribute('source', 'feed')
                ->child(XmlImporter::element($entry)),
        )->withoutDeclaration();

        self::assertSame(
            '<selection source="feed"><entry xmlns="' . self::FEED_NS . '" xmlns:dc="' . self::DC_NS . '" xmlns:media="' . self::MEDIA_NS . '" xmlns:xlink="' . self::XLINK_NS . '" xlink:href="https://example.com/products/item-1001"><title>Blue mug</title><dc:identifier>item-1001</dc:identifier><media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/></entry></selection>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItImportsAQueriedInvoiceSubtreeAndReserializesIt(): void
    {
        $readerDocument = XmlReader::fromString(
            $this->createInvoiceDocument()->toString(WriterConfig::compact()),
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

        $document = Xml::document(
            Xml::element('supplier-export')
                ->child(XmlImporter::element($supplierParty)),
        )->withoutDeclaration();

        self::assertSame(
            '<supplier-export><cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></supplier-export>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItStreamsImportedContentFromARealisticReaderWorkflow(): void
    {
        $readerDocument = XmlReader::fromString(
            $this->createInvoiceDocument()->toString(WriterConfig::compact()),
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

        $writer = StreamingXmlWriter::forString(
            WriterConfig::compact(emitDeclaration: false),
        );

        $writer
            ->startElement('supplier-export')
            ->writeElement(XmlImporter::element($supplierParty))
            ->endElement()
            ->finish();

        self::assertSame(
            '<supplier-export><cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID><cac:PartyName><cbc:Name>Muster Software GmbH</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty></supplier-export>',
            $writer->toString(),
        );
    }

    private function createCatalogDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element('catalog')
                ->attribute('generatedAt', '2026-04-18T10:30:00Z')
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

    private function createDefaultNamespaceFeedDocument(): XmlDocument
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

    private function createInvoiceDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element(Xml::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareDefaultNamespace(self::UBL_INVOICE_NS)
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->attribute(Xml::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml'), 'de')
                ->attribute(
                    Xml::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
                )
                ->child(Xml::element(Xml::qname('ID', self::UBL_CBC_NS, 'cbc'))->text('RE-2026-0042'))
                ->child(Xml::element(Xml::qname('IssueDate', self::UBL_CBC_NS, 'cbc'))->text('2026-04-17'))
                ->child(
                    Xml::element(Xml::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))
                        ->child(
                            Xml::element(Xml::qname('Party', self::UBL_CAC_NS, 'cac'))
                                ->child(
                                    Xml::element(Xml::qname('EndpointID', self::UBL_CBC_NS, 'cbc'))
                                        ->attribute('schemeID', '0088')
                                        ->text('0409876543210'),
                                )
                                ->child(
                                    Xml::element(Xml::qname('PartyName', self::UBL_CAC_NS, 'cac'))
                                        ->child(Xml::element(Xml::qname('Name', self::UBL_CBC_NS, 'cbc'))->text('Muster Software GmbH')),
                                ),
                        ),
                ),
        )->withoutDeclaration();
    }
}
