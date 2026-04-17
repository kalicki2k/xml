<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

final class XmlReaderQueryIntegrationTest extends TestCase
{
    public function testItQueriesSimpleCatalogDocumentsFromDocumentAndElementContexts(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<catalog generatedAt="2026-04-17T10:30:00Z">
    <book isbn="9780132350884" available="true">
        <title>Clean Code</title>
        <price currency="EUR">39.90</price>
    </book>
    <book isbn="9780321125217" available="false">
        <title>Domain-Driven Design</title>
        <price currency="EUR">54.90</price>
    </book>
</catalog>
XML,
        );

        $availableBooks = $document->findAll('//book[@available="true"]');
        $firstBook = $document->findFirst('/catalog/book[@isbn="9780132350884"]');

        self::assertCount(1, $availableBooks);
        self::assertNotNull($firstBook);
        $price = $firstBook->findFirst('./price');

        self::assertNotNull($price);
        self::assertSame('9780132350884', $firstBook->attributeValue('isbn'));
        self::assertSame('Clean Code', $firstBook->findFirst('./title')?->text());
        self::assertSame('39.90', $price->text());
        self::assertSame('EUR', $price->attributeValue('currency'));
    }

    public function testItQueriesDefaultNamespaceDocumentsWithExplicitAliases(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink">
    <entry xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
    </entry>
</feed>
XML,
        );

        $namespaces = [
            'feed' => 'urn:feed',
            'dc' => 'urn:dc',
            'xlink' => 'urn:xlink',
        ];

        $entry = $document->findFirst('/feed:feed/feed:entry[@xlink:href]', $namespaces);

        self::assertNotNull($entry);
        self::assertSame('Blue mug', $entry->findFirst('./feed:title', $namespaces)?->text());
        self::assertSame(
            'item-1001',
            $document->findFirst('/feed:feed/feed:entry[1]/dc:identifier', $namespaces)?->text(),
        );
        self::assertSame(
            'https://example.com/items/1',
            $entry->attributeValue(new QualifiedName('href', 'urn:xlink', 'xlink')),
        );
    }

    public function testItUsesInScopePrefixesForPrefixedNamespaceQueries(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<atom:feed xmlns:atom="urn:feed" xmlns:xlink="urn:xlink">
    <atom:entry xlink:href="https://example.com/items/1">
        <atom:title>Example entry</atom:title>
    </atom:entry>
</atom:feed>
XML,
        );

        $entry = $document->findFirst('//atom:entry[@xlink:href]');

        self::assertNotNull($entry);
        self::assertSame('atom:entry', $entry->name());
        self::assertSame('Example entry', $entry->findFirst('./atom:title')?->text());
        self::assertSame(
            'https://example.com/items/1',
            $entry->attributeValue(new QualifiedName('href', 'urn:xlink', 'xlink')),
        );
    }

    public function testItQueriesMixedNamespacedAndNonNamespacedStructures(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<catalog xmlns:dc="urn:dc">
    <book>
        <title>Clean Code</title>
        <dc:identifier>item-1001</dc:identifier>
    </book>
</catalog>
XML,
        );

        $book = $document->findFirst('/catalog/book');

        self::assertNotNull($book);
        self::assertSame('Clean Code', $document->findFirst('//book/title')?->text());
        self::assertSame(
            'item-1001',
            $document->findFirst('//dc:identifier', ['dc' => 'urn:dc'])?->text(),
        );
        self::assertSame('Clean Code', $book->findFirst('./title')?->text());
        self::assertSame(
            'item-1001',
            $book->findFirst('./dc:identifier', ['dc' => 'urn:dc'])?->text(),
        );
    }

    public function testItSupportsElementScopedNamespaceAwareQueriesWithExplicitAliases(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed" xmlns:dc="urn:dc">
    <entry>
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
    </entry>
</feed>
XML,
        );

        $namespaces = [
            'feed' => 'urn:feed',
            'dc' => 'urn:dc',
        ];

        $entry = $document->findFirst('/feed:feed/feed:entry', $namespaces);

        self::assertNotNull($entry);
        self::assertSame('Blue mug', $entry->findFirst('./feed:title', $namespaces)?->text());
        self::assertSame(
            'item-1001',
            $entry->findFirst('./dc:identifier', $namespaces)?->text(),
        );
    }

    public function testItQueriesRealisticInvoiceContentWithNamespaceAliases(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2 UBL-Invoice-2.1.xsd">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID>
            <cac:PartyName>
                <cbc:Name>Muster Software GmbH</cbc:Name>
            </cac:PartyName>
        </cac:Party>
    </cac:AccountingSupplierParty>
</Invoice>
XML,
        );

        $namespaces = [
            'inv' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        ];

        $supplier = $document->findFirst(
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party',
            $namespaces,
        );

        self::assertNotNull($supplier);
        $endpoint = $supplier->findFirst('./cbc:EndpointID', $namespaces);

        self::assertNotNull($endpoint);
        self::assertSame(
            'RE-2026-0042',
            $document->findFirst('/inv:Invoice/cbc:ID', $namespaces)?->text(),
        );
        self::assertSame('0409876543210', $endpoint->text());
        self::assertSame('0088', $endpoint->attributeValue('schemeID'));
        self::assertSame(
            'Muster Software GmbH',
            $supplier->findFirst('./cac:PartyName/cbc:Name', $namespaces)?->text(),
        );
    }
}
