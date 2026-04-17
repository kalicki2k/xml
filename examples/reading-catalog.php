<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    <<<'XML'
<catalog generatedAt="2026-04-17T10:30:00Z">
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

foreach ($document->rootElement()->childElements('book') as $book) {
    $title = $book->firstChildElement('title')?->text() ?? 'unknown';
    $price = $book->firstChildElement('price');

    echo sprintf(
        "%s | %s | %s %s\n",
        $book->attributeValue('isbn') ?? 'n/a',
        $title,
        $price?->attributeValue('currency') ?? '',
        $price?->text() ?? '',
    );
}
