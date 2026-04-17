<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$books = [
    [
        'isbn' => '9780132350884',
        'title' => 'Clean Code',
        'price' => '39.90',
    ],
    [
        'isbn' => '9780321125217',
        'title' => 'Domain-Driven Design',
        'price' => '54.90',
    ],
];

$path = tempnam(sys_get_temp_dir(), 'kalle-xml-example-');

if ($path === false) {
    throw new RuntimeException('Unable to allocate a temporary file path for XML output.');
}

$writer = StreamingXmlWriter::forFile($path, WriterConfig::pretty());

$writer
    ->startDocument()
    ->startElement('catalog')
    ->writeComment('nightly export');

foreach ($books as $book) {
    $writer->writeElement(
        Xml::element('book')
            ->attribute('isbn', $book['isbn'])
            ->child(Xml::element('title')->text($book['title']))
            ->child(Xml::element('price')->attribute('currency', 'EUR')->text($book['price'])),
    );
}

$writer->endElement()->finish();

echo sprintf("Wrote XML to %s\n", $path);
