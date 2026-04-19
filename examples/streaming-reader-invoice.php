<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\StreamingXmlReader;
use Kalle\Xml\Writer\XmlWriter;

$stream = fopen('php://temp', 'wb+');

if (!is_resource($stream)) {
    throw new RuntimeException('Could not open a temporary XML stream.');
}

fwrite(
    $stream,
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
rewind($stream);

try {
    $reader = StreamingXmlReader::fromStream($stream);

    while ($reader->read()) {
        if (!$reader->isStartElement(XmlBuilder::qname(
            'AccountingSupplierParty',
            'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            'cac',
        ))) {
            continue;
        }

        $supplierParty = $reader->expandElement();

        echo XmlWriter::toString(
            XmlBuilder::document(
                XmlImporter::element($supplierParty),
            )->withoutDeclaration(),
        ) . "\n";

        break;
    }
} finally {
    fclose($stream);
}
