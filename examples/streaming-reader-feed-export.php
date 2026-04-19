<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\StreamingXmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$stream = fopen('php://temp', 'wb+');

if (!is_resource($stream)) {
    throw new RuntimeException('Could not open a temporary XML stream.');
}

fwrite(
    $stream,
    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:media="urn:media" xmlns:xlink="urn:xlink">
    <entry sku="item-1001" xlink:href="https://example.com/products/item-1001">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1001.jpg" width="320" height="180"/>
    </entry>
    <entry sku="item-1002" xlink:href="https://example.com/products/item-1002">
        <title>Notebook set</title>
        <dc:identifier>item-1002</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1002.jpg" width="320" height="180"/>
    </entry>
    <entry sku="item-1003" xlink:href="https://example.com/products/item-1003">
        <title>Desk lamp</title>
        <dc:identifier>item-1003</dc:identifier>
        <media:thumbnail xlink:href="https://cdn.example.com/products/item-1003.jpg" width="320" height="180"/>
    </entry>
</feed>
XML,
);
rewind($stream);

try {
    $reader = StreamingXmlReader::fromStream($stream);
    $output = fopen('php://stdout', 'wb');

    if (!is_resource($output)) {
        throw new RuntimeException('Could not open stdout for filtered XML output.');
    }

    $writer = StreamingXmlWriter::forStream(
        $output,
        WriterConfig::compact(emitDeclaration: false),
    );

    $writer->startElement('selection');

    while ($reader->read()) {
        if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
            continue;
        }

        if ($reader->attributeValue('sku') === 'item-1002') {
            continue;
        }

        $writer->writeElement(
            XmlImporter::element($reader->expandElement())->attribute('selected', true),
        );
    }

    $writer->endElement()->finish();
    fwrite($output, "\n");
} finally {
    fclose($stream);
}
