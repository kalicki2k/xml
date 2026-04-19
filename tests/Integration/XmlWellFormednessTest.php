<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use DOMDocument;
use DOMElement;
use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use LibXMLError;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function sprintf;
use function trim;

final class XmlWellFormednessTest extends TestCase
{
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testPrettyPrintedNamespacedOutputIsAcceptedByDomDocument(): void
    {
        $xml = XmlWriter::toString(
            XmlBuilder::document(
                XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                    ->declareNamespace('atom', 'urn:feed')
                    ->declareNamespace('xlink', 'urn:xlink')
                    ->child(
                        XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                            ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
                            ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Example entry')),
                    ),
            )->withoutDeclaration(),
            WriterConfig::pretty(emitDeclaration: false),
        );

        $dom = $this->loadXml($xml);
        $root = $dom->documentElement;

        self::assertInstanceOf(DOMElement::class, $root);
        self::assertSame('feed', $root->localName);
        self::assertSame('urn:feed', $root->namespaceURI);
        self::assertSame('urn:xlink', $root->getAttribute('xmlns:xlink'));

        $entries = $dom->getElementsByTagNameNS('urn:feed', 'entry');
        self::assertSame(1, $entries->length);

        $entry = $entries->item(0);
        self::assertInstanceOf(DOMElement::class, $entry);
        self::assertSame('https://example.com/items/1', $entry->getAttributeNS('urn:xlink', 'href'));
    }

    public function testRealisticInvoiceOutputIsAcceptedByDomDocument(): void
    {
        $xml = XmlWriter::toString(
            XmlBuilder::document(
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
            ),
            WriterConfig::pretty(),
        );

        $dom = $this->loadXml($xml);
        $root = $dom->documentElement;

        self::assertInstanceOf(DOMElement::class, $root);
        self::assertSame('Invoice', $root->localName);
        self::assertSame(self::UBL_INVOICE_NS, $root->namespaceURI);
        self::assertSame(
            self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
            $root->getAttributeNS(self::XSI_NS, 'schemaLocation'),
        );

        $documentIds = $dom->getElementsByTagNameNS(self::UBL_CBC_NS, 'ID');
        self::assertSame(1, $documentIds->length);
        self::assertSame('RE-2026-0042', trim((string) $documentIds->item(0)?->textContent));

        $endpointIds = $dom->getElementsByTagNameNS(self::UBL_CBC_NS, 'EndpointID');
        self::assertSame(1, $endpointIds->length);

        $endpointId = $endpointIds->item(0);
        self::assertInstanceOf(DOMElement::class, $endpointId);
        self::assertSame('0088', $endpointId->getAttribute('schemeID'));
        self::assertSame('0409876543210', trim($endpointId->textContent));
    }

    private function loadXml(string $xml): DOMDocument
    {
        if (!class_exists(DOMDocument::class)) {
            self::markTestSkipped('ext-dom is required for parser-backed integration tests.');
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument('1.0', 'UTF-8');

        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        self::assertTrue($loaded, $this->formatLibxmlErrors($errors));

        return $dom;
    }

    /**
     * @param list<LibXMLError> $errors
     */
    private function formatLibxmlErrors(array $errors): string
    {
        if ($errors === []) {
            return 'Expected DOMDocument to accept the serialized XML as well-formed.';
        }

        $messages = [];

        foreach ($errors as $error) {
            $messages[] = sprintf(
                'line %d, column %d: %s',
                $error->line,
                $error->column,
                trim($error->message),
            );
        }

        return 'DOMDocument rejected the serialized XML: ' . implode(' | ', $messages);
    }
}
