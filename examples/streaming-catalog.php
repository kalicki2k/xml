<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$books = [
    [
        'isbn' => '9780132350884',
        'title' => 'Clean Code',
        'author' => 'Robert C. Martin',
        'available' => true,
        'price' => '39.90',
    ],
    [
        'isbn' => '9780321125217',
        'title' => 'Domain-Driven Design',
        'author' => 'Eric Evans',
        'available' => false,
        'price' => '54.90',
    ],
    [
        'isbn' => '9780321127426',
        'title' => 'Patterns of Enterprise Application Architecture',
        'author' => 'Martin Fowler',
        'available' => true,
        'price' => '49.00',
    ],
];

$stdout = fopen('php://stdout', 'wb');

if ($stdout === false) {
    throw new RuntimeException('Unable to open php://stdout for XML streaming.');
}

$writer = StreamingXmlWriter::forStream($stdout, WriterConfig::pretty());

$writer
    ->startDocument()
    ->startElement('catalog')
    ->writeComment('nightly export');

foreach ($books as $book) {
    $writer
        ->startElement('book')
        ->writeAttribute('isbn', $book['isbn'])
        ->writeAttribute('available', $book['available'])
        ->startElement('title')
        ->writeText($book['title'])
        ->endElement()
        ->startElement('author')
        ->writeText($book['author'])
        ->endElement()
        ->startElement('price')
        ->writeAttribute('currency', 'EUR')
        ->writeText($book['price'])
        ->endElement()
        ->endElement();
}

$writer->endElement()->finish();
