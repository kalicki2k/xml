<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;

$document = XmlReader::fromString(
    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID>
            <cac:PartyName>
                <cbc:Name>Muster Software GmbH</cbc:Name>
            </cac:PartyName>
        </cac:Party>
    </cac:AccountingSupplierParty>
</Invoice>
XML,
);

$queryNamespaces = [
    'inv' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
    'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
];

$supplierParty = $document->findFirst('/inv:Invoice/cac:AccountingSupplierParty', $queryNamespaces);

if ($supplierParty === null) {
    throw new RuntimeException('Expected the example invoice to contain one supplier subtree.');
}

$stream = fopen('php://output', 'wb');

if ($stream === false) {
    throw new RuntimeException('Unable to open php://output for imported XML streaming.');
}

$writer = StreamingXmlWriter::forStream($stream);

$writer
    ->startDocument()
    ->startElement('supplier-export')
    ->writeElement(XmlImporter::element($supplierParty))
    ->endElement()
    ->finish();
