<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780132350884')
                ->attribute('available', true)
                ->child(XmlBuilder::element('title')->text('Clean Code'))
                ->child(XmlBuilder::element('author')->text('Robert C. Martin'))
                ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('39.90')),
        )
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780321125217')
                ->attribute('available', false)
                ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                ->child(XmlBuilder::element('author')->text('Eric Evans'))
                ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90')),
        ),
);

echo XmlWriter::toString($document, WriterConfig::pretty());
