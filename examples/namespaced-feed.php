<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlBuilder::document(
    XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
        ->declareNamespace('atom', 'urn:feed')
        ->declareNamespace('xlink', 'urn:xlink')
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                ->attribute(
                    XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                    'https://example.com/items/1',
                )
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed', 'atom'))->text('Example entry')),
        ),
)->withoutDeclaration();

echo XmlWriter::toString($document, WriterConfig::pretty(emitDeclaration: false));
