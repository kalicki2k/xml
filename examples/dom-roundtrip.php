<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;

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

$entry = XmlReader::fromDomDocument($domDocument)->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);

if ($entry === null) {
    throw new RuntimeException('Expected the DOM-backed feed to contain the queried entry.');
}

echo Xml::document(
    XmlImporter::element($entry)->attribute('exported', true),
)->withoutDeclaration()->toString() . "\n";
