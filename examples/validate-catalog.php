<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Validation\XmlValidator;

$validator = XmlValidator::fromString(
    <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
                            <xs:element name="price" type="xs:decimal"/>
                        </xs:sequence>
                        <xs:attribute name="isbn" type="xs:string" use="required"/>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
);

$document = Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(Xml::element('title')->text('Clean Code'))
                ->child(Xml::element('price')->text('39.90')),
        ),
);

// Validate the writer-built document directly; no manual intermediate string is needed.
$result = $validator->validateXmlDocument($document);

if (!$result->isValid()) {
    throw new RuntimeException(
        'Expected the example catalog to be valid. '
        . ($result->firstError()?->__toString() ?? 'No validation diagnostics were returned.'),
    );
}

echo "catalog is valid\n";
