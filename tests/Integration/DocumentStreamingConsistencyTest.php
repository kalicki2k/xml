<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function is_resource;
use function rewind;
use function str_replace;
use function stream_get_contents;
use function trim;

final class DocumentStreamingConsistencyTest extends TestCase
{
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testSimpleElementsAttributesAndTextMatchBetweenDocumentAndStreamingWriter(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $this->assertStreamedXmlMatchesDocument($document, WriterConfig::compact(), static function (StreamingXmlWriter $writer): void {
            $writer
                ->startDocument()
                ->startElement('catalog')
                ->writeAttribute('generatedAt', '2026-04-17T10:30:00Z')
                ->startElement('book')
                ->writeAttribute('isbn', '9780132350884')
                ->writeText('Clean Code')
                ->endElement()
                ->endElement();
        });
    }

    public function testEscapingCommentsCdataAndProcessingInstructionsMatch(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('payload')
                ->attribute('title', 'Fish & "Chips"' . "\n" . '<today>')
                ->comment('generated export')
                ->child(XmlBuilder::element('body')->text('Use < & enjoy'))
                ->child(XmlBuilder::element('script')->child(XmlBuilder::cdata('if (a < b && c > d) { return "ok"; }')))
                ->processingInstruction('cache-control', 'ttl="300"'),
        )->withoutDeclaration();

        $this->assertStreamedXmlMatchesDocument(
            $document,
            WriterConfig::compact(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement('payload')
                    ->writeAttribute('title', 'Fish & "Chips"' . "\n" . '<today>')
                    ->writeComment('generated export')
                    ->startElement('body')
                    ->writeText('Use < & enjoy')
                    ->endElement()
                    ->startElement('script')
                    ->writeCdata('if (a < b && c > d) { return "ok"; }')
                    ->endElement()
                    ->writeProcessingInstruction('cache-control', 'ttl="300"')
                    ->endElement();
            },
        );
    }

    public function testDefaultAndPrefixedNamespacesMatchBetweenDocumentAndStreamingWriter(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed'))
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                        ->attribute(
                            XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                            'https://example.com/items/1',
                        ),
                )
                ->child(XmlBuilder::element('meta')->text('plain')),
        )->withoutDeclaration();

        $this->assertStreamedXmlMatchesDocument(
            $document,
            WriterConfig::compact(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement(XmlBuilder::qname('feed', 'urn:feed'))
                    ->startElement(XmlBuilder::qname('entry', 'urn:feed'))
                    ->writeAttribute(
                        XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                        'https://example.com/items/1',
                    )
                    ->endElement()
                    ->startElement('meta')
                    ->writeText('plain')
                    ->endElement()
                    ->endElement();
            },
        );
    }

    public function testPrettyPrintedRealisticCatalogMatchesAfterWhitespaceNormalization(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->comment('nightly export')
                ->processingInstruction('cache-control', 'ttl="300"')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('sku', 'bk-001')
                        ->attribute('available', true)
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                        ->child(XmlBuilder::element('author')->text('Eric Evans'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('sku', 'bk-002')
                        ->attribute('available', false)
                        ->child(XmlBuilder::element('title')->text('Patterns of Enterprise Application Architecture'))
                        ->child(XmlBuilder::element('author')->text('Martin Fowler'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('49.00')),
                ),
        )->withoutDeclaration();

        $this->assertStreamedXmlMatchesDocument(
            $document,
            WriterConfig::pretty(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement('catalog')
                    ->writeComment('nightly export')
                    ->writeProcessingInstruction('cache-control', 'ttl="300"')
                    ->startElement('book')
                    ->writeAttribute('sku', 'bk-001')
                    ->writeAttribute('available', true)
                    ->startElement('title')
                    ->writeText('Domain-Driven Design')
                    ->endElement()
                    ->startElement('author')
                    ->writeText('Eric Evans')
                    ->endElement()
                    ->startElement('price')
                    ->writeAttribute('currency', 'EUR')
                    ->writeText('54.90')
                    ->endElement()
                    ->endElement()
                    ->startElement('book')
                    ->writeAttribute('sku', 'bk-002')
                    ->writeAttribute('available', false)
                    ->startElement('title')
                    ->writeText('Patterns of Enterprise Application Architecture')
                    ->endElement()
                    ->startElement('author')
                    ->writeText('Martin Fowler')
                    ->endElement()
                    ->startElement('price')
                    ->writeAttribute('currency', 'EUR')
                    ->writeText('49.00')
                    ->endElement()
                    ->endElement()
                    ->endElement();
            },
            normalize: true,
        );
    }

    public function testNamespaceHeavyInvoiceMatchesBetweenDocumentAndStreamingWriter(): void
    {
        $document = $this->createInvoiceDocument();

        $this->assertStreamedXmlMatchesDocument($document, WriterConfig::pretty(), function (StreamingXmlWriter $writer): void {
            $writer
                ->startDocument()
                ->startElement(XmlBuilder::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->writeAttribute(
                    XmlBuilder::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
                )
                ->startElement(XmlBuilder::qname('CustomizationID', self::UBL_CBC_NS, 'cbc'))
                ->writeText('urn:cen.eu:en16931:2017')
                ->endElement()
                ->startElement(XmlBuilder::qname('ID', self::UBL_CBC_NS, 'cbc'))
                ->writeText('RE-2026-0042')
                ->endElement()
                ->startElement(XmlBuilder::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))
                ->startElement(XmlBuilder::qname('Party', self::UBL_CAC_NS, 'cac'))
                ->startElement(XmlBuilder::qname('EndpointID', self::UBL_CBC_NS, 'cbc'))
                ->writeAttribute('schemeID', '0088')
                ->writeText('0409876543210')
                ->endElement()
                ->startElement(XmlBuilder::qname('PartyName', self::UBL_CAC_NS, 'cac'))
                ->startElement(XmlBuilder::qname('Name', self::UBL_CBC_NS, 'cbc'))
                ->writeText('Muster Software GmbH')
                ->endElement()
                ->endElement()
                ->endElement()
                ->endElement()
                ->endElement();
        }, normalize: true);
    }

    /**
     * @param callable(StreamingXmlWriter): void $write
     */
    private function assertStreamedXmlMatchesDocument(
        XmlDocument $document,
        WriterConfig $config,
        callable $write,
        bool $normalize = false,
    ): void {
        $expected = XmlWriter::toString($document, $config);
        $actual = $this->streamToString($config, $write);

        if ($normalize) {
            $expected = $this->normalizeXmlString($expected);
            $actual = $this->normalizeXmlString($actual);
        }

        self::assertSame($expected, $actual);
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

    private function createInvoiceDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->attribute(
                    XmlBuilder::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
                )
                ->child(XmlBuilder::element(XmlBuilder::qname('CustomizationID', self::UBL_CBC_NS, 'cbc'))->text('urn:cen.eu:en16931:2017'))
                ->child(XmlBuilder::element(XmlBuilder::qname('ID', self::UBL_CBC_NS, 'cbc'))->text('RE-2026-0042'))
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
        );
    }

    private function normalizeXmlString(string $xml): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $xml));
    }
}
