<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->attribute('available', true)
                ->child(Xml::element('title')->text('Clean Code'))
                ->child(Xml::element('author')->text('Robert C. Martin'))
                ->child(Xml::element('price')->attribute('currency', 'EUR')->text('39.90')),
        )
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780321125217')
                ->attribute('available', false)
                ->child(Xml::element('title')->text('Domain-Driven Design'))
                ->child(Xml::element('author')->text('Eric Evans'))
                ->child(Xml::element('price')->attribute('currency', 'EUR')->text('54.90')),
        ),
);

echo $document->toString(WriterConfig::pretty());
