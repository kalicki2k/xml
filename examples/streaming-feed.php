<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$entries = [
    [
        'id' => 'item-1001',
        'title' => 'Blue mug',
        'href' => 'https://example.com/products/item-1001',
        'thumbnail' => 'https://cdn.example.com/products/item-1001.jpg',
    ],
    [
        'id' => 'item-1002',
        'title' => 'Notebook set',
        'href' => 'https://example.com/products/item-1002',
        'thumbnail' => 'https://cdn.example.com/products/item-1002.jpg',
    ],
    [
        'id' => 'item-1003',
        'title' => 'Desk lamp',
        'href' => 'https://example.com/products/item-1003',
        'thumbnail' => 'https://cdn.example.com/products/item-1003.jpg',
    ],
];

$stdout = fopen('php://stdout', 'wb');

if ($stdout === false) {
    throw new RuntimeException('Unable to open php://stdout for namespace-aware XML streaming.');
}

$writer = StreamingXmlWriter::forStream($stdout, WriterConfig::pretty());

$writer
    ->startDocument()
    ->startElement(Xml::qname('feed', 'urn:feed'))
    ->declareDefaultNamespace('urn:feed')
    ->declareNamespace('dc', 'urn:dc')
    ->declareNamespace('media', 'urn:media')
    ->declareNamespace('xlink', 'urn:xlink')
    ->writeComment('incremental product feed');

foreach ($entries as $entry) {
    $writer
        ->startElement(Xml::qname('entry', 'urn:feed'))
        ->writeAttribute(Xml::qname('href', 'urn:xlink', 'xlink'), $entry['href'])
        ->startElement(Xml::qname('title', 'urn:feed'))
        ->writeText($entry['title'])
        ->endElement()
        ->startElement(Xml::qname('identifier', 'urn:dc', 'dc'))
        ->writeText($entry['id'])
        ->endElement()
        ->startElement(Xml::qname('thumbnail', 'urn:media', 'media'))
        ->writeAttribute(Xml::qname('href', 'urn:xlink', 'xlink'), $entry['thumbnail'])
        ->writeAttribute('width', 320)
        ->writeAttribute('height', 180)
        ->endElement()
        ->endElement();
}

$writer->endElement()->finish();
