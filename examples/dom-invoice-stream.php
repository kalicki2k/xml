<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\StreamingXmlWriter;

$domDocument = new DOMDocument('1.0', 'UTF-8');
$loaded = $domDocument->loadXML(
    <<<'XML'
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cbc:IssueDate>2026-04-17</cbc:IssueDate>
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

if ($loaded !== true) {
    throw new RuntimeException('Expected the example invoice to parse into DOM.');
}

$supplierParty = $domDocument->getElementsByTagNameNS(
    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    'AccountingSupplierParty',
)->item(0);

if (!$supplierParty instanceof DOMElement) {
    throw new RuntimeException('Expected the example invoice to contain one supplier subtree.');
}

$stream = fopen('php://output', 'wb');

if ($stream === false) {
    throw new RuntimeException('Unable to open php://output for DOM-based XML streaming.');
}

$writer = StreamingXmlWriter::forStream($stream);

$writer
    ->startDocument()
    ->startElement('supplier-export')
    ->writeElement(XmlImporter::element(XmlReader::fromDomElement($supplierParty)))
    ->endElement()
    ->finish();
