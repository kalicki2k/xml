<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function rewind;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class XmlReaderIntegrationTest extends TestCase
{
    public function testItTraversesSimpleChildElementsAndAttributes(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<catalog generatedAt="2026-04-17T10:30:00Z">
    <!--nightly export-->
    <book isbn="9780132350884">
        <title>Clean Code</title>
        <price currency="EUR">39.90</price>
    </book>
    <book isbn="9780321125217">
        <title>Domain-Driven Design</title>
        <price currency="EUR">54.90</price>
    </book>
</catalog>
XML,
        );

        $root = $document->rootElement();
        $books = $root->childElements('book');
        $firstBook = $root->firstChildElement('book');

        self::assertSame('catalog', $root->name());
        self::assertSame('catalog', $root->localName());
        self::assertNull($root->prefix());
        self::assertNull($root->namespaceUri());
        self::assertNull($root->parent());
        self::assertCount(1, $root->attributes());
        self::assertSame('generatedAt', $root->attributes()[0]->name());
        self::assertTrue($root->hasAttribute('generatedAt'));
        self::assertSame('2026-04-17T10:30:00Z', $root->attributeValue('generatedAt'));
        self::assertNull($root->attributeValue('missing'));
        self::assertFalse($root->hasAttribute('missing'));
        self::assertCount(2, $root->childElements());
        self::assertCount(2, $books);
        self::assertNotNull($firstBook);
        self::assertSame('9780132350884', $firstBook->attributeValue('isbn'));
        $title = $firstBook->firstChildElement('title');

        self::assertNotNull($title);
        self::assertSame('Clean Code', $title->text());
        self::assertSame('book', $title->parent()?->name());
    }

    public function testItSupportsNamespaceAwareTraversalAndAttributesFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-read-');

        self::assertNotFalse($path);

        self::assertNotFalse(file_put_contents(
            $path,
            <<<'XML'
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink">
    <entry xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
    </entry>
</feed>
XML,
        ));

        try {
            $document = XmlReader::fromFile($path);
            $root = $document->rootElement();
            $entry = $root->firstChildElement(XmlBuilder::qname('entry', 'urn:feed', 'atom'));
            $declarations = $root->namespacesInScope();
            $entries = $root->childElements(XmlBuilder::qname('entry', 'urn:feed'));

            self::assertSame('feed', $root->name());
            self::assertSame('urn:feed', $root->namespaceUri());
            self::assertCount(0, $root->attributes());
            self::assertCount(1, $entries);
            self::assertCount(3, $declarations);
            self::assertNull($declarations[0]->prefix());
            self::assertSame('urn:feed', $declarations[0]->uri());
            self::assertSame('dc', $declarations[1]->prefix());
            self::assertSame('xlink', $declarations[2]->prefix());
            self::assertNotNull($entry);
            self::assertCount(1, $entry->attributes());
            self::assertTrue($entry->hasAttribute(XmlBuilder::qname('href', 'urn:xlink', 'link')));
            self::assertSame(
                'https://example.com/items/1',
                $entry->attributeValue(XmlBuilder::qname('href', 'urn:xlink', 'link')),
            );
            self::assertNull($entry->attributeValue('missing'));
            self::assertFalse($entry->hasAttribute('missing'));
            self::assertSame(
                'Blue mug',
                $entry->firstChildElement(XmlBuilder::qname('title', 'urn:feed'))?->text(),
            );
            self::assertSame(
                'item-1001',
                $entry->firstChildElement(XmlBuilder::qname('identifier', 'urn:dc', 'meta'))?->text(),
            );
        } finally {
            @unlink($path);
        }
    }

    public function testDefaultNamespaceAppliesToElementsButNotAutomaticallyToAttributes(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<feed xmlns="urn:feed">
    <entry rel="self"/>
</feed>
XML,
        );

        $root = $document->rootElement();
        $entry = $root->firstChildElement(XmlBuilder::qname('entry', 'urn:feed'));

        self::assertNotNull($entry);
        self::assertSame('urn:feed', $entry->namespaceUri());
        self::assertSame('self', $entry->attributeValue('rel'));
        self::assertNull($entry->attributeValue(XmlBuilder::qname('rel', 'urn:feed', 'feed')));
        self::assertFalse($entry->hasAttribute(XmlBuilder::qname('rel', 'urn:feed', 'feed')));
    }

    public function testItTraversesPrefixedChildElementsAndNamespacedAttributesFromAStream(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xml:lang="de">
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
        ));

        rewind($stream);

        try {
            $document = XmlReader::fromStream($stream);
            $root = $document->rootElement();
            $supplier = $root
                ->firstChildElement(XmlBuilder::qname('AccountingSupplierParty', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac'))
                ?->firstChildElement(XmlBuilder::qname('Party', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac'));

            self::assertSame('Invoice', $root->name());
            self::assertSame(
                'de',
                $root->attributeValue(XmlBuilder::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml')),
            );
            self::assertCount(1, $root->attributes());
            self::assertCount(3, $root->namespacesInScope());
            self::assertSame(
                'RE-2026-0042',
                $root->firstChildElement(XmlBuilder::qname('ID', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc'))?->text(),
            );
            self::assertNotNull($supplier);
            self::assertCount(
                1,
                $root->childElements(XmlBuilder::qname('AccountingSupplierParty', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2')),
            );
            $endpoint = $supplier->firstChildElement(XmlBuilder::qname('EndpointID', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc'));
            $partyName = $supplier->firstChildElement(XmlBuilder::qname('PartyName', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac'));
            $supplierName = $partyName?->firstChildElement(XmlBuilder::qname('Name', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc'));

            self::assertNotNull($endpoint);
            self::assertSame('0409876543210', $endpoint->text());
            self::assertTrue($endpoint->hasAttribute('schemeID'));
            self::assertSame('0088', $endpoint->attributeValue('schemeID'));
            self::assertNotNull($partyName);
            self::assertNotNull($supplierName);
            self::assertSame('Muster Software GmbH', $supplierName->text());
        } finally {
            fclose($stream);
        }
    }
}
