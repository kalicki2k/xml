<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\StreamingNodeType;
use Kalle\Xml\Reader\StreamingXmlReader;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Validation\XmlValidator;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function rewind;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class StreamingXmlReaderIntegrationTest extends TestCase
{
    public function testItReadsDefaultNamespaceElementsWithoutApplyingThatNamespaceToPlainAttributes(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<feed xmlns="urn:feed">
    <entry rel="self" xml:lang="en">Notebook set</entry>
</feed>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);

            while ($reader->read()) {
                if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
                    continue;
                }

                self::assertSame('entry', $reader->name());
                self::assertSame('entry', $reader->localName());
                self::assertNull($reader->prefix());
                self::assertSame('urn:feed', $reader->namespaceUri());
                self::assertTrue($reader->hasAttribute('rel'));
                self::assertSame('self', $reader->attributeValue('rel'));
                self::assertFalse($reader->hasAttribute(XmlBuilder::qname('rel', 'urn:feed', 'feed')));
                self::assertNull($reader->attributeValue(XmlBuilder::qname('rel', 'urn:feed', 'feed')));
                self::assertSame(
                    'en',
                    $reader->attributeValue(XmlBuilder::qname('lang', 'http://www.w3.org/XML/1998/namespace', 'xml')),
                );

                return;
            }

            self::fail('Expected a namespaced <entry> element in the streamed document.');
        } finally {
            fclose($stream);
        }
    }

    public function testItReadsPrefixedAttributesAndListsCurrentAttributes(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink" xmlns:media="urn:media">
    <entry sku="item-1002" xlink:href="https://example.com/items/2" media:width="640"/>
</feed>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);

            while ($reader->read()) {
                if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
                    continue;
                }

                $attributes = $reader->attributes();

                self::assertCount(3, $attributes);
                self::assertTrue($reader->hasAttribute('sku'));
                self::assertTrue($reader->hasAttribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink')));
                self::assertTrue($reader->hasAttribute(XmlBuilder::qname('width', 'urn:media', 'media')));
                self::assertSame('item-1002', $reader->attributeValue('sku'));
                self::assertSame(
                    'https://example.com/items/2',
                    $reader->attributeValue(XmlBuilder::qname('href', 'urn:xlink', 'xlink')),
                );
                self::assertSame(
                    '640',
                    $reader->attributeValue(XmlBuilder::qname('width', 'urn:media', 'media')),
                );
                self::assertNull($reader->attributeValue('missing'));
                self::assertFalse($reader->hasAttribute('missing'));
                self::assertSame(
                    [
                        ['sku', null, null, 'item-1002'],
                        ['href', 'xlink', 'urn:xlink', 'https://example.com/items/2'],
                        ['width', 'media', 'urn:media', '640'],
                    ],
                    array_map(
                        static fn ($attribute): array => [
                            $attribute->localName(),
                            $attribute->prefix(),
                            $attribute->namespaceUri(),
                            $attribute->value(),
                        ],
                        $attributes,
                    ),
                );

                return;
            }

            self::fail('Expected a namespaced <entry> element with prefixed attributes.');
        } finally {
            fclose($stream);
        }
    }

    public function testItReadsMixedNamespacedAndNonNamespacedStructures(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<root xmlns:cfg="urn:cfg">
    <cfg:item scope="namespaced">A</cfg:item>
    <item scope="plain">B</item>
</root>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $seen = [];

            while ($reader->read()) {
                if ($reader->isStartElement(XmlBuilder::qname('item', 'urn:cfg', 'cfg'))) {
                    $seen[] = [
                        'kind' => 'namespaced',
                        'name' => $reader->name(),
                        'prefix' => $reader->prefix(),
                        'namespace' => $reader->namespaceUri(),
                        'scope' => $reader->attributeValue('scope'),
                    ];

                    continue;
                }

                if ($reader->isStartElement('item')) {
                    $seen[] = [
                        'kind' => 'plain',
                        'name' => $reader->name(),
                        'prefix' => $reader->prefix(),
                        'namespace' => $reader->namespaceUri(),
                        'scope' => $reader->attributeValue('scope'),
                    ];
                }
            }

            self::assertSame([
                [
                    'kind' => 'namespaced',
                    'name' => 'cfg:item',
                    'prefix' => 'cfg',
                    'namespace' => 'urn:cfg',
                    'scope' => 'namespaced',
                ],
                [
                    'kind' => 'plain',
                    'name' => 'item',
                    'prefix' => null,
                    'namespace' => null,
                    'scope' => 'plain',
                ],
            ], $seen);
        } finally {
            fclose($stream);
        }
    }

    public function testItExtractsNamespacedSubtreesAsXmlForReaderImporterAndValidatorWorkflows(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink" xmlns:media="urn:media"><meta generatedAt="2026-04-18T12:00:00Z"/><entry sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title><media:thumbnail media:width="640">thumb-2.jpg</media:thumbnail></entry><entry sku="item-1003" xlink:href="https://example.com/items/3"><title>Pencil case</title><media:thumbnail media:width="320">thumb-3.jpg</media:thumbnail></entry></feed>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $extractedXml = null;
            $importedXml = null;
            $validated = null;

            while ($reader->read()) {
                if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
                    continue;
                }

                if ($reader->attributeValue('sku') !== 'item-1002') {
                    continue;
                }

                self::assertSame('entry', $reader->localName());
                self::assertSame(1, $reader->depth());

                $extractedXml = $reader->extractElementXml();

                self::assertSame('item-1002', $reader->attributeValue('sku'));
                self::assertTrue($reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed')));

                $document = XmlReader::fromString($extractedXml);
                $entry = $document->rootElement();

                self::assertSame('entry', $entry->name());
                self::assertSame('urn:feed', $entry->namespaceUri());
                self::assertSame(
                    'https://example.com/items/2',
                    $entry->attributeValue(XmlBuilder::qname('href', 'urn:xlink', 'xlink')),
                );
                self::assertSame(
                    'thumb-2.jpg',
                    $entry->findFirst('./media:thumbnail', ['media' => 'urn:media'])?->text(),
                );

                $importedXml = XmlWriter::toString(
                    XmlBuilder::document(
                        XmlImporter::element($entry)->attribute('exported', true),
                    )->withoutDeclaration(),
                    WriterConfig::compact(emitDeclaration: false),
                );

                $validator = XmlValidator::fromString(
                    <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:feed="urn:feed" xmlns:xlink="urn:xlink" xmlns:media="urn:media" targetNamespace="urn:feed" elementFormDefault="qualified" attributeFormDefault="unqualified">
    <xs:import namespace="urn:xlink"/>
    <xs:import namespace="urn:media"/>
    <xs:element name="entry">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="title" type="xs:string"/>
                <xs:any namespace="urn:media" processContents="skip" minOccurs="1" maxOccurs="1"/>
            </xs:sequence>
            <xs:attribute name="sku" type="xs:string" use="required"/>
            <xs:anyAttribute namespace="##other" processContents="skip"/>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
                );

                $validated = $validator->validateString($extractedXml)->isValid();

                self::assertTrue($reader->read());
                self::assertTrue($reader->isStartElement(XmlBuilder::qname('title', 'urn:feed')));

                break;
            }

            self::assertSame(
                '<entry xmlns="urn:feed" xmlns:xlink="urn:xlink" xmlns:media="urn:media" sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title><media:thumbnail media:width="640">thumb-2.jpg</media:thumbnail></entry>',
                $extractedXml,
            );
            self::assertSame(
                '<entry xmlns="urn:feed" xmlns:media="urn:media" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2" exported="true"><title>Notebook set</title><media:thumbnail media:width="640">thumb-2.jpg</media:thumbnail></entry>',
                $importedXml,
            );
            self::assertTrue($validated);
        } finally {
            fclose($stream);
        }
    }

    public function testNodeTypeHelpersDistinguishCommentsCdataTextAndElementBoundaries(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<payload><!--note--><script><![CDATA[if (a < b) return "ok";]]></script><title>Hello</title></payload>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $seen = [];

            while ($reader->read()) {
                if ($reader->isComment()) {
                    $seen[] = ['comment', $reader->value()];
                    self::assertFalse($reader->isText());
                    self::assertFalse($reader->isCdata());

                    continue;
                }

                if ($reader->isCdata()) {
                    $seen[] = ['cdata', $reader->value()];
                    self::assertFalse($reader->isText());
                    self::assertFalse($reader->isComment());

                    continue;
                }

                if ($reader->isText()) {
                    $seen[] = ['text', $reader->value()];
                    self::assertFalse($reader->isComment());
                    self::assertFalse($reader->isCdata());

                    continue;
                }

                if ($reader->isStartElement('script')) {
                    $seen[] = ['start', $reader->name()];

                    continue;
                }

                if ($reader->isEndElement('script')) {
                    $seen[] = ['end', $reader->name()];
                }
            }

            self::assertSame([
                ['comment', 'note'],
                ['start', 'script'],
                ['cdata', 'if (a < b) return "ok";'],
                ['end', 'script'],
                ['text', 'Hello'],
            ], $seen);
        } finally {
            fclose($stream);
        }
    }

    public function testItReadsCatalogLikeXmlIncrementallyFromAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-stream-read-');

        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents(
            $path,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalog generatedAt="2026-04-18T11:00:00Z">
    <!--nightly export-->
    <?cache-control ttl="300"?>
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
        ));

        try {
            $reader = StreamingXmlReader::fromFile($path);
            $books = [];
            $commentSeen = false;
            $processingInstructionSeen = false;
            $textValues = [];

            self::assertTrue($reader->isOpen());
            self::assertFalse($reader->hasCurrentNode());
            self::assertNull($reader->nodeType());

            while ($reader->read()) {
                if ($reader->nodeType() === StreamingNodeType::Comment) {
                    $commentSeen = true;
                    self::assertSame('nightly export', $reader->value());
                }

                if ($reader->nodeType() === StreamingNodeType::ProcessingInstruction) {
                    $processingInstructionSeen = true;
                    self::assertSame('cache-control', $reader->name());
                    self::assertSame('ttl="300"', $reader->value());
                }

                if ($reader->nodeType() === StreamingNodeType::Text) {
                    $textValues[] = $reader->value();
                }

                if (!$reader->isStartElement('book')) {
                    continue;
                }

                $book = $reader->expandElement();
                $books[] = [
                    'isbn' => $reader->attributeValue('isbn'),
                    'title' => $book->firstChildElement('title')?->text(),
                    'price' => $book->firstChildElement('price')?->text(),
                    'currency' => $book->firstChildElement('price')?->attributeValue('currency'),
                    'depth' => $reader->depth(),
                ];
            }

            self::assertTrue($commentSeen);
            self::assertTrue($processingInstructionSeen);
            self::assertContains('Clean Code', $textValues);
            self::assertContains('54.90', $textValues);
            self::assertSame([
                [
                    'isbn' => '9780132350884',
                    'title' => 'Clean Code',
                    'price' => '39.90',
                    'currency' => 'EUR',
                    'depth' => 1,
                ],
                [
                    'isbn' => '9780321125217',
                    'title' => 'Domain-Driven Design',
                    'price' => '54.90',
                    'currency' => 'EUR',
                    'depth' => 1,
                ],
            ], $books);
            self::assertFalse($reader->hasCurrentNode());
        } finally {
            @unlink($path);
        }
    }

    public function testItExpandsNamespacedStreamSubtreesIntoTheExistingReaderAndImportFlow(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="urn:feed" xmlns:media="urn:media" xmlns:xlink="urn:xlink">
    <entry sku="item-1002" xlink:href="https://example.com/items/2">
        <title>Notebook set</title>
        <media:thumbnail media:width="640">thumb-2.jpg</media:thumbnail>
    </entry>
    <entry sku="item-1003" xlink:href="https://example.com/items/3">
        <title>Pencil case</title>
        <media:thumbnail media:width="320">thumb-3.jpg</media:thumbnail>
    </entry>
</feed>
XML,
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $importedDocument = null;

            while ($reader->read()) {
                if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
                    continue;
                }

                if ($reader->attributeValue('sku') !== 'item-1002') {
                    continue;
                }

                $entry = $reader->expandElement();
                $thumbnail = $entry->findFirst('./media:thumbnail', [
                    'media' => 'urn:media',
                ]);

                self::assertSame('item-1002', $entry->attributeValue('sku'));
                self::assertSame(
                    'https://example.com/items/2',
                    $entry->attributeValue(XmlBuilder::qname('href', 'urn:xlink', 'xlink')),
                );
                self::assertNotNull($thumbnail);
                self::assertSame(
                    '640',
                    $thumbnail->attributeValue(XmlBuilder::qname('width', 'urn:media', 'media')),
                );
                self::assertSame('thumb-2.jpg', $thumbnail->text());

                $importedDocument = XmlWriter::toString(
                    XmlBuilder::document(
                        XmlImporter::element($entry)->attribute('exported', true),
                    )->withoutDeclaration(),
                    WriterConfig::compact(emitDeclaration: false),
                );

                break;
            }

            self::assertSame(
                '<entry xmlns="urn:feed" xmlns:media="urn:media" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2" exported="true"><title>Notebook set</title><media:thumbnail media:width="640">thumb-2.jpg</media:thumbnail></entry>',
                $importedDocument,
            );
        } finally {
            fclose($stream);
        }
    }

    public function testItReportsParseErrorsWithSourceContext(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite(
            $stream,
            '<feed><entry><title>Broken</entry></feed>',
        ));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            try {
                while ($reader->read()) {
                }

                self::fail('Expected malformed XML to raise a ParseException.');
            } catch (ParseException $exception) {
                self::assertStringContainsString('Malformed XML in stream "php://temp".', $exception->getMessage());
                self::assertStringContainsString('line 1', $exception->getMessage());
                self::assertStringContainsString('entry', $exception->getMessage());
                self::assertFalse($reader->isOpen());
            }
        } finally {
            fclose($stream);
        }
    }
}
