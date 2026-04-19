<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Reader\XmlReader;

$stream = fopen('php://temp', 'wb+');

if ($stream === false) {
    throw new RuntimeException('Unable to open a temporary stream for XML input.');
}

$written = fwrite(
    $stream,
    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:ID>RE-2026-0042</cbc:ID>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0088">0409876543210</cbc:EndpointID>
        </cac:Party>
    </cac:AccountingSupplierParty>
</Invoice>
XML,
);

if ($written === false) {
    throw new RuntimeException('Unable to write XML example data to the temporary stream.');
}

rewind($stream);

try {
    $document = XmlReader::fromStream($stream);
    $root = $document->rootElement();
    $endpoint = $root
        ->firstChildElement(XmlBuilder::qname('AccountingSupplierParty', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac'))
        ?->firstChildElement(XmlBuilder::qname('Party', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac'))
        ?->firstChildElement(XmlBuilder::qname('EndpointID', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc'));

    echo sprintf(
        "%s | %s\n",
        $root->firstChildElement(XmlBuilder::qname('ID', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc'))?->text() ?? 'n/a',
        $endpoint?->attributeValue('schemeID') ?? 'n/a',
    );
} finally {
    fclose($stream);
}
