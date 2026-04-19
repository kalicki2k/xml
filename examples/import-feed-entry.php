<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlReader::fromString(
    <<<'XML'
<feed xmlns="urn:feed" xmlns:xlink="urn:xlink">
    <entry sku="item-1001" xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
    </entry>
    <entry sku="item-1002" xlink:href="https://example.com/items/2">
        <title>Notebook set</title>
    </entry>
</feed>
XML,
);

$queryNamespaces = [
    'feed' => 'urn:feed',
    'xlink' => 'urn:xlink',
];

$entry = $document->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', $queryNamespaces);

if ($entry === null) {
    throw new RuntimeException('Expected the example feed to contain the queried entry.');
}

$writerElement = XmlImporter::element($entry)
    ->attribute('exported', true)
    ->attribute('sku', 'item-1002-copy');

echo XmlWriter::toString(
    XmlBuilder::document($writerElement)->withoutDeclaration(),
) . "\n";
