<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element(Xml::qname('feed', 'urn:feed', 'atom'))
        ->declareNamespace('atom', 'urn:feed')
        ->declareNamespace('xlink', 'urn:xlink')
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed', 'atom'))
                ->attribute(
                    Xml::qname('href', 'urn:xlink', 'xlink'),
                    'https://example.com/items/1',
                )
                ->child(Xml::element(Xml::qname('title', 'urn:feed', 'atom'))->text('Example entry')),
        ),
)->withoutDeclaration();

echo $document->toString(WriterConfig::pretty(emitDeclaration: false));
