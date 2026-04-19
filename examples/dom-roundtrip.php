<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\XmlWriter;

$writerDocument = XmlBuilder::document(
    XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1001')
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Blue mug')),
        )
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1002')
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Notebook set')),
        ),
);

$domDocument = XmlDomBridge::toDomDocument($writerDocument);

$entry = XmlReader::fromDomDocument($domDocument)->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);

if ($entry === null) {
    throw new RuntimeException('Expected the DOM-backed feed to contain the queried entry.');
}

echo XmlWriter::toString(
    XmlBuilder::document(
        XmlImporter::element($entry)->attribute('exported', true),
    )->withoutDeclaration(),
) . "\n";
