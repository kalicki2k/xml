<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Reader\XmlReader;

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

$namespaces = [
    'inv' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
    'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
];

$supplier = $document->findFirst('/inv:Invoice/cac:AccountingSupplierParty/cac:Party', $namespaces);

if ($supplier === null) {
    throw new RuntimeException('Expected the example invoice to contain one supplier party.');
}

$invoiceId = $document->findFirst('/inv:Invoice/cbc:ID', $namespaces);
$endpoint = $supplier->findFirst('./cbc:EndpointID[@schemeID="0088"]');
$supplierName = $supplier->findFirst('./cac:PartyName/cbc:Name');

if ($invoiceId === null || $endpoint === null || $supplierName === null) {
    throw new RuntimeException('Expected the example invoice to contain the queried supplier data.');
}

echo sprintf(
    "%s | %s | %s\n",
    $invoiceId->text(),
    $endpoint->text(),
    $supplierName->text(),
);
