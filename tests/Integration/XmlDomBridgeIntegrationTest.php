<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMAttr;
use DOMElement;
use DOMProcessingInstruction;
use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class XmlDomBridgeIntegrationTest extends TestCase
{
    public function testItExportsXmlDocumentsIntoDomDocuments(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $domDocument = XmlDomBridge::toDomDocument($document);
        $root = $domDocument->documentElement;

        self::assertInstanceOf(DOMDocument::class, $domDocument);
        self::assertNotNull($root);
        self::assertSame('1.0', $domDocument->xmlVersion);
        self::assertSame('UTF-8', $domDocument->encoding);
        $book = $root->firstChild;
        self::assertInstanceOf(DOMElement::class, $book);
        self::assertSame('catalog', $root->tagName);
        self::assertSame('book', $book->nodeName);
        self::assertSame('9780132350884', $book->attributes->getNamedItem('isbn')?->nodeValue);
        $serialized = $domDocument->saveXML();
        self::assertIsString($serialized);
        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<catalog><book isbn="9780132350884">Clean Code</book></catalog>',
            trim($serialized),
        );
    }

    public function testItExportsNamespaceAwareWriterTreesIntoDomWithResolvedNamespaces(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed'))
                ->declareDefaultNamespace('urn:feed')
                ->child(Xml::element('entry'))
                ->child(Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))),
        )->withoutDeclaration();

        $domDocument = XmlDomBridge::toDomDocument($document);
        $root = $domDocument->documentElement;

        self::assertNotNull($root);
        self::assertSame('urn:feed', $root->namespaceURI);
        self::assertSame('urn:feed', $root->getAttribute('xmlns'));

        $entry = $root->firstChild;
        self::assertInstanceOf(DOMElement::class, $entry);
        self::assertNull($entry->namespaceURI);
        self::assertSame('', $entry->getAttribute('xmlns'));

        $thumbnail = $entry->nextSibling;
        while ($thumbnail !== null && !$thumbnail instanceof DOMElement) {
            $thumbnail = $thumbnail->nextSibling;
        }

        self::assertInstanceOf(DOMElement::class, $thumbnail);
        self::assertSame('media:thumbnail', $thumbnail->tagName);
        self::assertSame('urn:media', $thumbnail->namespaceURI);
        self::assertSame('urn:media', $thumbnail->getAttribute('xmlns:media'));
    }

    public function testItExportsCommentsCdataProcessingInstructionsAndMixedContentIntoDom(): void
    {
        $document = Xml::document(
            Xml::element('payload')
                ->comment('generated export')
                ->child(
                    Xml::element('script')
                        ->cdata('if (a < b && c > d) { return "ok"; }'),
                )
                ->processingInstruction('cache-control', 'ttl="300"')
                ->child(
                    Xml::element('p')
                        ->text('Hello ')
                        ->child(Xml::element('strong')->text('world'))
                        ->text('!'),
                ),
        )->withoutDeclaration();

        $domDocument = XmlDomBridge::toDomDocument($document);
        $root = $domDocument->documentElement;

        self::assertNotNull($root);
        self::assertInstanceOf(DOMComment::class, $root->childNodes->item(0));
        self::assertSame('generated export', $root->childNodes->item(0)->nodeValue);

        $script = $root->childNodes->item(1);
        self::assertInstanceOf(DOMElement::class, $script);
        self::assertInstanceOf(DOMCdataSection::class, $script->childNodes->item(0));
        self::assertSame('if (a < b && c > d) { return "ok"; }', $script->childNodes->item(0)->nodeValue);

        self::assertInstanceOf(DOMProcessingInstruction::class, $root->childNodes->item(2));
        self::assertSame('cache-control', $root->childNodes->item(2)->nodeName);
        self::assertSame('ttl="300"', $root->childNodes->item(2)->nodeValue);

        $paragraph = $root->childNodes->item(3);
        self::assertInstanceOf(DOMElement::class, $paragraph);
        $paragraphText = $paragraph->childNodes->item(0);
        $strong = $paragraph->childNodes->item(1);
        $punctuation = $paragraph->childNodes->item(2);
        self::assertNotNull($paragraphText);
        self::assertInstanceOf(DOMElement::class, $strong);
        self::assertNotNull($punctuation);
        self::assertSame('p', $paragraph->tagName);
        self::assertSame('Hello ', $paragraphText->nodeValue);
        self::assertSame('strong', $strong->nodeName);
        self::assertSame('world', $strong->textContent);
        self::assertSame('!', $punctuation->nodeValue);
    }

    public function testItExportsWriterElementsIntoDomDocuments(): void
    {
        $element = Xml::element(Xml::qname('entry', 'urn:feed'))
            ->declareDefaultNamespace('urn:feed')
            ->attribute('sku', 'item-1002')
            ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set'));

        $domDocument = XmlDomBridge::elementToDomDocument($element);
        $documentRoot = $domDocument->documentElement;

        self::assertNotNull($documentRoot);
        self::assertSame('entry', $documentRoot->tagName);
        self::assertSame('urn:feed', $documentRoot->namespaceURI);
        self::assertSame('item-1002', $documentRoot->getAttribute('sku'));
        self::assertSame(
            '<entry xmlns="urn:feed" sku="item-1002"><title>Notebook set</title></entry>',
            $domDocument->saveXML($documentRoot),
        );

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $readerElement = XmlReader::fromDomElement($documentRoot);

        self::assertSame('entry', $readerDocument->rootElement()->name());
        self::assertSame('item-1002', $readerDocument->rootElement()->attributeValue('sku'));
        self::assertSame('entry', $readerElement->name());
        self::assertSame('item-1002', $readerElement->attributeValue('sku'));
        self::assertSame(
            'Notebook set',
            $readerDocument->findFirst('/feed:entry/feed:title', ['feed' => 'urn:feed'])?->text(),
        );
        self::assertSame(
            'Notebook set',
            $readerElement->findFirst('./feed:title', ['feed' => 'urn:feed'])?->text(),
        );
    }

    public function testItPreservesPrefixedAttributesAndDefaultNamespaceSemanticsAcrossWriterDomReaderRoundtrip(): void
    {
        $writerDocument = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed'))
                ->declareDefaultNamespace('urn:feed')
                ->declareNamespace('xlink', 'urn:xlink')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed'))
                        ->attribute('sku', 'item-1002')
                        ->attribute(
                            Xml::qname('href', 'urn:xlink', 'xlink'),
                            'https://example.com/items/2',
                        )
                        ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set')),
                ),
        )->withoutDeclaration();

        $domDocument = XmlDomBridge::toDomDocument($writerDocument);
        $root = $domDocument->documentElement;

        self::assertNotNull($root);

        $entry = $root->firstChild;

        self::assertInstanceOf(DOMElement::class, $entry);
        self::assertSame('urn:feed', $root->namespaceURI);
        self::assertSame('urn:feed', $entry->namespaceURI);
        self::assertSame('item-1002', $entry->getAttribute('sku'));
        $plainAttribute = $entry->getAttributeNode('sku');
        self::assertInstanceOf(DOMAttr::class, $plainAttribute);
        self::assertNull($plainAttribute->namespaceURI);
        self::assertNull($entry->getAttributeNodeNS('urn:feed', 'sku'));
        self::assertSame('https://example.com/items/2', $entry->getAttributeNS('urn:xlink', 'href'));
        self::assertSame('xlink:href', $entry->getAttributeNodeNS('urn:xlink', 'href')?->nodeName);

        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $queriedEntry = $readerDocument->findFirst('/feed:feed/feed:entry[@xlink:href]', [
            'feed' => 'urn:feed',
            'xlink' => 'urn:xlink',
        ]);

        self::assertNotNull($queriedEntry);
        self::assertSame('item-1002', $queriedEntry->attributeValue('sku'));
        self::assertSame(
            'https://example.com/items/2',
            $queriedEntry->attributeValue(Xml::qname('href', 'urn:xlink', 'xlink')),
        );

        $imported = XmlImporter::document($readerDocument)->withoutDeclaration();

        self::assertSame(
            '<feed xmlns="urn:feed" xmlns:xlink="urn:xlink"><entry sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title></entry></feed>',
            $imported->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItPreservesMixedNestedNamespaceScopesAcrossWriterDomReaderImportRoundtrip(): void
    {
        $writerDocument = Xml::document(
            Xml::element(Xml::qname('catalog', 'urn:catalog'))
                ->declareDefaultNamespace('urn:catalog')
                ->child(
                    Xml::element(Xml::qname('item', 'urn:catalog'))
                        ->attribute('sku', 'item-1001')
                        ->child(Xml::element(Xml::qname('title', 'urn:catalog'))->text('Notebook'))
                        ->child(
                            Xml::element(Xml::qname('flag', 'urn:catalog-meta', 'meta'))
                                ->declareNamespace('meta', 'urn:catalog-meta')
                                ->attribute(Xml::qname('code', 'urn:catalog-meta', 'meta'), 'featured')
                                ->text('yes'),
                        ),
                )
                ->child(
                    Xml::element(Xml::qname('bundle', 'urn:bundle'))
                        ->declareDefaultNamespace('urn:bundle')
                        ->child(Xml::element(Xml::qname('title', 'urn:bundle'))->text('Starter bundle'))
                        ->child(
                            Xml::element(Xml::qname('flag', 'urn:bundle-meta', 'meta'))
                                ->declareNamespace('meta', 'urn:bundle-meta')
                                ->attribute(Xml::qname('code', 'urn:bundle-meta', 'meta'), 'bundle')
                                ->text('special'),
                        ),
                ),
        )->withoutDeclaration();

        $domDocument = XmlDomBridge::toDomDocument($writerDocument);
        $root = $domDocument->documentElement;

        self::assertNotNull($root);

        $firstItem = $root->childNodes->item(0);
        $bundle = $root->childNodes->item(1);

        self::assertInstanceOf(DOMElement::class, $firstItem);
        self::assertInstanceOf(DOMElement::class, $bundle);
        $bundleTitle = $bundle->childNodes->item(0);
        $bundleFlag = $bundle->childNodes->item(1);
        self::assertInstanceOf(DOMElement::class, $bundleTitle);
        self::assertInstanceOf(DOMElement::class, $bundleFlag);
        self::assertSame('urn:catalog', $root->namespaceURI);
        self::assertSame('urn:catalog', $firstItem->namespaceURI);
        self::assertSame('urn:bundle', $bundle->namespaceURI);
        self::assertSame('urn:bundle', $bundle->getAttribute('xmlns'));
        self::assertSame('title', $bundleTitle->tagName);
        self::assertSame('bundle', $bundleFlag->getAttributeNS('urn:bundle-meta', 'code'));

        $imported = XmlImporter::document(
            XmlReader::fromDomDocument($domDocument),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><item sku="item-1001"><title>Notebook</title><meta:flag xmlns:meta="urn:catalog-meta" meta:code="featured">yes</meta:flag></item><bundle xmlns="urn:bundle"><title>Starter bundle</title><meta:flag xmlns:meta="urn:bundle-meta" meta:code="bundle">special</meta:flag></bundle></catalog>',
            $imported->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }

    public function testItSupportsElementDomReaderStreamingWriterWorkflowsWithNonElementContent(): void
    {
        $element = Xml::element(Xml::qname('entry', 'urn:feed'))
            ->declareDefaultNamespace('urn:feed')
            ->declareNamespace('xlink', 'urn:xlink')
            ->attribute('sku', 'item-1002')
            ->attribute(
                Xml::qname('href', 'urn:xlink', 'xlink'),
                'https://example.com/items/2',
            )
            ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set'))
            ->comment('generated export')
            ->child(
                Xml::element(Xml::qname('script', 'urn:feed'))
                    ->cdata('if (a < b && c > d) { return "ok"; }'),
            )
            ->processingInstruction('cache-control', 'ttl="300"');

        $domDocument = XmlDomBridge::elementToDomDocument($element);
        $documentRoot = $domDocument->documentElement;

        self::assertNotNull($documentRoot);

        $readerElement = XmlReader::fromDomElement($documentRoot);
        $writer = StreamingXmlWriter::forString(
            WriterConfig::compact(emitDeclaration: false),
        );

        $writer
            ->startElement('export')
            ->writeElement(XmlImporter::element($readerElement))
            ->endElement()
            ->finish();

        self::assertSame(
            '<export><entry xmlns="urn:feed" xmlns:xlink="urn:xlink" sku="item-1002" xlink:href="https://example.com/items/2"><title>Notebook set</title><!--generated export--><script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script><?cache-control ttl="300"?></entry></export>',
            $writer->toString(),
        );
    }

    public function testItSupportsWriterDomReaderQueryImportWriteWorkflows(): void
    {
        $writerDocument = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed'))
                ->declareDefaultNamespace('urn:feed')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed'))
                        ->attribute('sku', 'item-1001')
                        ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Blue mug')),
                )
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed'))
                        ->attribute('sku', 'item-1002')
                        ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set')),
                ),
        );

        $domDocument = XmlDomBridge::toDomDocument($writerDocument);
        $readerDocument = XmlReader::fromDomDocument($domDocument);
        $entry = $readerDocument->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
            'feed' => 'urn:feed',
        ]);

        self::assertNotNull($entry);

        $result = Xml::document(
            XmlImporter::element($entry)->attribute('exported', true),
        )->withoutDeclaration();

        self::assertSame(
            '<entry xmlns="urn:feed" sku="item-1002" exported="true"><title>Notebook set</title></entry>',
            $result->toString(WriterConfig::compact(emitDeclaration: false)),
        );
    }
}
