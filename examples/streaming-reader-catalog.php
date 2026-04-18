<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Reader\StreamingXmlReader;

$path = tempnam(sys_get_temp_dir(), 'kalle-xml-stream-reader-');

if ($path === false) {
    throw new RuntimeException('Could not create a temporary XML file.');
}

file_put_contents(
    $path,
    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalog generatedAt="2026-04-18T11:00:00Z">
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
);

try {
    $reader = StreamingXmlReader::fromFile($path);

    while ($reader->read()) {
        if (!$reader->isStartElement('book')) {
            continue;
        }

        $book = $reader->expandElement();
        $title = $book->firstChildElement('title')?->text() ?? 'unknown';

        echo $reader->attributeValue('isbn') . ': ' . $title . "\n";
    }
} finally {
    @unlink($path);
}
