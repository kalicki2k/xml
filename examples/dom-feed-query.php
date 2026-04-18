<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Reader\XmlReader;

$writerDocument = Xml::document(
    Xml::element(Xml::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->declareNamespace('dc', 'urn:dc')
        ->declareNamespace('media', 'urn:media')
        ->declareNamespace('xlink', 'urn:xlink')
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/products/item-1001')
                ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Blue mug'))
                ->child(Xml::element(Xml::qname('identifier', 'urn:dc', 'dc'))->text('item-1001'))
                ->child(
                    Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))
                        ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://cdn.example.com/products/item-1001.jpg')
                        ->attribute('width', 320)
                        ->attribute('height', 180),
                ),
        )
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/products/item-1002')
                ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set'))
                ->child(Xml::element(Xml::qname('identifier', 'urn:dc', 'dc'))->text('item-1002')),
        ),
)->withoutDeclaration();

$domDocument = XmlDomBridge::toDomDocument($writerDocument);
$queryNamespaces = [
    'feed' => 'urn:feed',
    'dc' => 'urn:dc',
    'media' => 'urn:media',
    'xlink' => 'urn:xlink',
];

$entry = XmlReader::fromDomDocument($domDocument)->findFirst(
    '/feed:feed/feed:entry[@xlink:href]',
    $queryNamespaces,
);

if ($entry === null) {
    throw new RuntimeException('Expected the DOM-backed feed to contain at least one entry.');
}

echo sprintf(
    "%s | %s | %s\n",
    $entry->findFirst('./feed:title', $queryNamespaces)?->text() ?? 'unknown',
    $entry->findFirst('./dc:identifier', $queryNamespaces)?->text() ?? 'n/a',
    $entry->attributeValue(Xml::qname('href', 'urn:xlink', 'xlink')) ?? 'n/a',
);
