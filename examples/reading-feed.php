<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Reader\XmlReader;

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="urn:feed" xmlns:dc="urn:dc" xmlns:xlink="urn:xlink">
    <entry xlink:href="https://example.com/items/1">
        <title>Blue mug</title>
        <dc:identifier>item-1001</dc:identifier>
    </entry>
</feed>
XML;

$path = tempnam(sys_get_temp_dir(), 'kalle-xml-read-');

if ($path === false) {
    throw new RuntimeException('Unable to allocate a temporary file path for XML input.');
}

file_put_contents($path, $xml);

try {
    $document = XmlReader::fromFile($path);
    $entry = $document->rootElement()->firstChildElement(Xml::qname('entry', 'urn:feed'));

    if ($entry !== null) {
        echo sprintf(
            "%s | %s | %s\n",
            $entry->firstChildElement(Xml::qname('title', 'urn:feed'))?->text() ?? 'unknown',
            $entry->firstChildElement(Xml::qname('identifier', 'urn:dc', 'dc'))?->text() ?? 'n/a',
            $entry->attributeValue(Xml::qname('href', 'urn:xlink', 'xlink')) ?? 'n/a',
        );
    }
} finally {
    @unlink($path);
}
