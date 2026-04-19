<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Validation\XmlValidator;

$validator = XmlValidator::fromString(
    <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:feed" xmlns:feed="urn:feed" elementFormDefault="qualified">
    <xs:element name="feed">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="entry" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
                        </xs:sequence>
                        <xs:attribute name="sku" type="xs:string" use="required"/>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
);

$document = XmlBuilder::document(
    XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1001')
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Blue mug')),
        )
        ->child(
            XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1002')
                ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Notebook set')),
        ),
);

// Validate the writer-built, namespace-aware document directly.
$result = $validator->validateXmlDocument($document);

if (!$result->isValid()) {
    throw new RuntimeException(
        'Expected the example feed to be valid. '
        . ($result->firstError()?->__toString() ?? 'No validation diagnostics were returned.'),
    );
}

echo "feed is valid\n";
