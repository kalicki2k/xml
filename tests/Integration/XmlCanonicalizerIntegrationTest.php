<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use DOMDocument;
use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Canonicalization\CanonicalizationOptions;
use Kalle\Xml\Canonicalization\XmlCanonicalizer;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

final class XmlCanonicalizerIntegrationTest extends TestCase
{
    private const FEED_NS = 'urn:feed';
    private const DC_NS = 'urn:dc';
    private const XLINK_NS = 'urn:xlink';
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function testItCanonicalizesEquivalentWriterReaderImporterAndDomFlowsToTheSameOutput(): void
    {
        $writerDocument = $this->createFeedDocument();
        $serialized = XmlWriter::toString(
            $writerDocument,
            WriterConfig::compact(emitDeclaration: false),
        );
        $readerDocument = XmlReader::fromString($serialized);
        $importedDocument = XmlImporter::document($readerDocument)->withoutDeclaration();
        $domDocument = XmlDomBridge::toDomDocument($writerDocument);
        $expected = '<feed xmlns="urn:feed" xmlns:dc="' . self::DC_NS . '" xmlns:xlink="' . self::XLINK_NS . '"><entry sku="item-1002" xlink:href="https://example.com/products/item-1002"><title>Notebook set</title><dc:identifier>item-1002</dc:identifier></entry></feed>';

        self::assertSame($expected, XmlCanonicalizer::document($writerDocument));
        self::assertSame($expected, XmlCanonicalizer::readerDocument($readerDocument));
        self::assertSame($expected, XmlCanonicalizer::document($importedDocument));
        self::assertSame($expected, XmlCanonicalizer::domDocument($domDocument));
        self::assertSame($expected, XmlCanonicalizer::xmlString($serialized));
    }

    public function testItCanonicalizesReaderElementsAsStandaloneSubtrees(): void
    {
        $readerDocument = XmlReader::fromString($this->invoiceXml());
        $supplierParty = $readerDocument->findFirst(
            '/inv:Invoice/cac:AccountingSupplierParty',
            [
                'inv' => self::UBL_INVOICE_NS,
                'cac' => self::UBL_CAC_NS,
                'cbc' => self::UBL_CBC_NS,
            ],
        );

        self::assertNotNull($supplierParty);
        self::assertSame(
            '<cac:AccountingSupplierParty xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '"><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID></cac:Party></cac:AccountingSupplierParty>',
            XmlCanonicalizer::readerElement($supplierParty),
        );
    }

    public function testItOptionallyIncludesCommentsDuringCanonicalization(): void
    {
        $xml = '<!--before--><catalog><!--inside--><book/></catalog><!--after-->';

        self::assertSame(
            '<catalog><book></book></catalog>',
            XmlCanonicalizer::xmlString($xml),
        );
        self::assertSame(
            "<!--before-->\n<catalog><!--inside--><book></book></catalog>\n<!--after-->",
            XmlCanonicalizer::xmlString($xml, CanonicalizationOptions::withComments()),
        );
    }

    public function testItCanonicalizesDomBackedXmlLoadedOutsideTheWriterFlow(): void
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $domDocument->loadXML(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink"><entry xlink:href="https://example.com/products/item-1002" sku="item-1002"><dc:identifier>item-1002</dc:identifier><title>Notebook set</title></entry></feed>
XML,
            LIBXML_NONET,
        );

        self::assertSame(
            '<feed xmlns="urn:feed" xmlns:dc="' . self::DC_NS . '" xmlns:xlink="' . self::XLINK_NS . '"><entry sku="item-1002" xlink:href="https://example.com/products/item-1002"><dc:identifier>item-1002</dc:identifier><title>Notebook set</title></entry></feed>',
            XmlCanonicalizer::domDocument($domDocument),
        );
    }

    private function createFeedDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', self::FEED_NS))
                ->declareDefaultNamespace(self::FEED_NS)
                ->declareNamespace('dc', self::DC_NS)
                ->declareNamespace('xlink', self::XLINK_NS)
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', self::FEED_NS))
                        ->attribute(
                            XmlBuilder::qname('href', self::XLINK_NS, 'xlink'),
                            'https://example.com/products/item-1002',
                        )
                        ->attribute('sku', 'item-1002')
                        ->child(XmlBuilder::element(XmlBuilder::qname('title', self::FEED_NS))->text('Notebook set'))
                        ->child(XmlBuilder::element(XmlBuilder::qname('identifier', self::DC_NS, 'dc'))->text('item-1002')),
                ),
        )->withoutDeclaration();
    }

    private function invoiceXml(): string
    {
        return <<<'XML'
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID>
        </cac:Party>
    </cac:AccountingSupplierParty>
</Invoice>
XML;
    }
}
