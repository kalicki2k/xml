<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
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

$document = Xml::document(
    Xml::element(Xml::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1001')
                ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Blue mug')),
        )
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->attribute('sku', 'item-1002')
                ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set')),
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
