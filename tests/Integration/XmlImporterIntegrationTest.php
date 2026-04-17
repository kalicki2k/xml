<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class XmlImporterIntegrationTest extends TestCase
{
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testItImportsSimpleReaderDocumentsIntoXmlDocuments(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                '<catalog generatedAt="2026-04-18T10:30:00Z"><book isbn="9780132350884">Clean Code</book></catalog>',
            ),
        );

        self::assertNull($document->declaration());
        self::assertSame(
            '<catalog generatedAt="2026-04-18T10:30:00Z"><book isbn="9780132350884">Clean Code</book></catalog>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItPreservesMeaningfulXmlDeclarationSettingsWhenImportingDocuments(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><catalog/>',
            ),
        );

        $declaration = $document->declaration();

        self::assertNotNull($declaration);
        self::assertSame('UTF-8', $declaration->encoding());
        self::assertTrue($declaration->standalone());
        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><catalog/>',
            $document->toString(),
        );
    }

    public function testItImportsNamespaceAwareReaderDocumentsIntoWriterDocuments(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                '<Invoice xmlns="' . self::UBL_INVOICE_NS . '" xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '" xmlns:xsi="' . self::XSI_NS . '" xsi:schemaLocation="' . self::UBL_INVOICE_NS . ' invoice.xsd"><cbc:ID>RE-2026-0042</cbc:ID><cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID></cac:Party></cac:AccountingSupplierParty></Invoice>',
            ),
        );

        self::assertSame(
            '<Invoice xmlns="' . self::UBL_INVOICE_NS . '" xmlns:cac="' . self::UBL_CAC_NS . '" xmlns:cbc="' . self::UBL_CBC_NS . '" xmlns:xsi="' . self::XSI_NS . '" xsi:schemaLocation="' . self::UBL_INVOICE_NS . ' invoice.xsd"><cbc:ID>RE-2026-0042</cbc:ID><cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID></cac:Party></cac:AccountingSupplierParty></Invoice>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItImportsQueryResultsIntoNamespaceAwareWriterElements(): void
    {
        $readerDocument = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink">
    <entry xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
    </entry>
</feed>
XML,
        );

        $entry = $readerDocument->findFirst('/feed:feed/feed:entry', [
            'feed' => 'urn:feed',
            'xlink' => 'urn:xlink',
        ]);

        self::assertNotNull($entry);

        $document = new XmlDocument(XmlImporter::element($entry), null);

        self::assertSame(
            '<entry xmlns="urn:feed" xmlns:xlink="urn:xlink" xlink:href="https://example.com/items/1"><title>Blue mug</title></entry>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItPreservesCommentsCdataProcessingInstructionsAndMixedContent(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                '<payload><!--generated export--><script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script><?cache-control ttl="300"?><p>Hello <strong>world</strong>!</p></payload>',
            ),
        );

        self::assertSame(
            '<payload><!--generated export--><script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script><?cache-control ttl="300"?><p>Hello <strong>world</strong>!</p></payload>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSkipsFormattingWhitespaceWhileKeepingStructuralContent(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                <<<'XML'
<catalog>
    <book isbn="9780132350884">
        <title>Clean Code</title>
    </book>
    <book isbn="9780321125217">
        <title>Domain-Driven Design</title>
    </book>
</catalog>
XML,
            ),
        );

        self::assertSame(
            '<catalog><book isbn="9780132350884"><title>Clean Code</title></book><book isbn="9780321125217"><title>Domain-Driven Design</title></book></catalog>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItPreservesMixedNestedNamespaceScopesDuringImport(): void
    {
        $document = XmlImporter::document(
            XmlReader::fromString(
                <<<'XML'
<catalog xmlns="urn:catalog">
    <item sku="item-1001">
        <title>Notebook</title>
        <meta:flag xmlns:meta="urn:catalog-meta" meta:code="featured">yes</meta:flag>
    </item>
    <bundle xmlns="urn:bundle">
        <title>Starter bundle</title>
        <meta:flag xmlns:meta="urn:bundle-meta" meta:code="bundle">special</meta:flag>
    </bundle>
</catalog>
XML,
            ),
        );

        self::assertSame(
            '<catalog xmlns="urn:catalog"><item sku="item-1001"><title>Notebook</title><meta:flag xmlns:meta="urn:catalog-meta" meta:code="featured">yes</meta:flag></item><bundle xmlns="urn:bundle"><title>Starter bundle</title><meta:flag xmlns:meta="urn:bundle-meta" meta:code="bundle">special</meta:flag></bundle></catalog>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSupportsReaderQueryToImportToWriteWorkflows(): void
    {
        $readerDocument = XmlReader::fromString($this->feedFixture());
        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
            'feed' => 'urn:feed',
        ]);

        self::assertNotNull($entry);

        $importedElement = XmlImporter::element($entry)
            ->attribute('exported', true)
            ->attribute('sku', 'item-1002-copy');

        $document = Xml::document($importedElement)->withoutDeclaration();

        self::assertSame(
            '<entry xmlns="urn:feed" sku="item-1002-copy" exported="true"><title>Notebook set</title></entry>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItImportsMultipleQueryResultsIntoANewWriterDocument(): void
    {
        $readerDocument = XmlReader::fromString($this->feedFixture());
        $entries = $readerDocument->findAll('/feed:feed/feed:entry', [
            'feed' => 'urn:feed',
        ]);

        self::assertCount(2, $entries);

        $selection = Xml::element('selection');

        foreach ($entries as $entry) {
            $selection = $selection->child(XmlImporter::element($entry));
        }

        $document = Xml::document($selection)->withoutDeclaration();

        self::assertSame(
            '<selection><entry xmlns="urn:feed" sku="item-1001"><title>Blue mug</title></entry><entry xmlns="urn:feed" sku="item-1002"><title>Notebook set</title></entry></selection>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItCanStreamImportedReaderElementsThroughTheStreamingWriter(): void
    {
        $readerDocument = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink">
    <entry sku="item-1002" xlink:href="https://example.com/items/2">
        <title>Notebook set</title>
    </entry>
</feed>
XML,
        );

        $entry = $readerDocument->findFirst('/feed:feed/feed:entry', [
            'feed' => 'urn:feed',
        ]);

        self::assertNotNull($entry);

        $writer = StreamingXmlWriter::forString(
            WriterConfig::compact(emitDeclaration: false),
        );

        $writer
            ->startElement('export')
            ->writeElement(XmlImporter::element($entry))
            ->endElement()
            ->finish();

        self::assertSame(
            '<export><entry xmlns="urn:feed" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title></entry></export>',
            $writer->toString(),
        );
    }

    public function testItImportsANamespacedQueryResultIntoAValidNamespacedWriterTree(): void
    {
        $readerDocument = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink">
    <entry sku="item-1002" xlink:href="https://example.com/items/2">
        <title>Notebook set</title>
    </entry>
</feed>
XML,
        );

        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@xlink:href]', [
            'feed' => 'urn:feed',
            'xlink' => 'urn:xlink',
        ]);

        self::assertNotNull($entry);

        $document = Xml::document(
            Xml::element(Xml::qname('selection', 'urn:export'))
                ->declareDefaultNamespace('urn:export')
                ->child(XmlImporter::element($entry)),
        )->withoutDeclaration();

        self::assertSame(
            '<selection xmlns="urn:export"><entry xmlns="urn:feed" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title></entry></selection>',
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    private function feedFixture(): string
    {
        return <<<'XML'
<feed xmlns="urn:feed">
    <entry sku="item-1001">
        <title>Blue mug</title>
    </entry>
    <entry sku="item-1002">
        <title>Notebook set</title>
    </entry>
</feed>
XML;
    }
}
