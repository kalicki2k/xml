<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Canonicalization\XmlCanonicalizer;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlBuilder::document(
    XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->declareNamespace('dc', 'urn:dc')
        ->declareNamespace('xlink', 'urn:xlink')
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/products/item-1002')
                ->attribute('sku', 'item-1002')
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Notebook set'))
                ->child(XmlBuilder::element(XmlBuilder::qname('identifier', 'urn:dc', 'dc'))->text('item-1002')),
        ),
)->withoutDeclaration();

$serialized = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));
$readerDocument = XmlReader::fromString($serialized);
$importedDocument = XmlImporter::document($readerDocument)->withoutDeclaration();
$domDocument = XmlDomBridge::toDomDocument($document);

$writerCanonical = XmlCanonicalizer::document($document);
$readerCanonical = XmlCanonicalizer::readerDocument($readerDocument);
$importedCanonical = XmlCanonicalizer::document($importedDocument);
$domCanonical = XmlCanonicalizer::domDocument($domDocument);

if (
    $writerCanonical !== $readerCanonical
    || $writerCanonical !== $importedCanonical
    || $writerCanonical !== $domCanonical
) {
    throw new RuntimeException('Canonical XML output diverged across package flows.');
}

echo $writerCanonical . "\n";
