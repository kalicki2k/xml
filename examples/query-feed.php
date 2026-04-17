<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink">
    <entry xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
    </entry>
</feed>
XML,
);

// XPath does not apply the XML default namespace automatically, so the feed
// namespace is mapped to an explicit query alias.
$queryNamespaces = [
    'feed' => 'urn:feed',
    'dc' => 'urn:dc',
    'xlink' => 'urn:xlink',
];

$entries = $document->findAll('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);
$entry = $entries[0] ?? null;

if ($entry === null) {
    throw new RuntimeException('Expected the example feed to contain one entry.');
}

echo sprintf(
    "%s | %s | %s\n",
    $entry->findFirst('./feed:title', $queryNamespaces)?->text() ?? 'unknown',
    $entry->findFirst('./dc:identifier', $queryNamespaces)?->text() ?? 'n/a',
    $entry->attributeValue(new QualifiedName('href', 'urn:xlink', 'xlink')) ?? 'n/a',
);
