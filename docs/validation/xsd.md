# Validate XML against XSD

Use `XmlValidator` when well-formed XML is not enough and you need explicit
XSD validation.

## What This Is For

Use validation when you want to:

- bind one XSD schema and validate multiple XML inputs against it
- validate strings, files, streams, or existing `XmlDocument` instances
- get a compact result object for invalid-but-well-formed XML

## When To Use It

Choose `XmlValidator` when schema validation matters, but you still want a
small API instead of a broader schema-processing framework.

## Create a Validator

- `XmlValidator::fromString(string $schema)`
- `XmlValidator::fromFile(string $path)`
- `XmlValidator::fromStream(mixed $stream)`

`fromFile()` keeps validation path-based, so relative `xs:include` and
`xs:import` locations continue to work.

## Validate XML

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Validation\XmlValidator;

$validator = XmlValidator::fromString(
    <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
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

$document = XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(XmlBuilder::element('title')->text('Clean Code')),
        ),
);

$result = $validator->validateXmlDocument($document);

if (!$result->isValid()) {
    echo $result->firstError() . "\n";
}
```

Validation entry points:

- `validateString(string $xml)`
- `validateFile(string $path)`
- `validateStream(mixed $stream)`
- `validateXmlDocument(XmlDocument $document)`

## Result and Error Handling

- Invalid but well-formed XML returns a `ValidationResult`.
- `ValidationResult::errors()` returns `ValidationError` objects.
- `ValidationResult::firstError()` returns the first diagnostic or `null`.
- `ValidationError` exposes `message()`, `line()`, `column()`, and `level()`.

Malformed XML still throws `ParseException`. Unreadable XML or XSD inputs still
throw file or stream exceptions. Invalid XSD schemas throw
`InvalidSchemaException`.

## Boundaries

The validation surface is intentionally compact:

- it covers XSD validation, not a broader schema framework
- it does not add mutation or transformation behavior
- malformed XML and invalid schemas still fail fast with exceptions
- if you need to build or transform XML before validation, use `XmlBuilder` or `XmlImporter` first

## Related

- [Validation guides](README.md)
- [Writer guides](../writer/README.md)
- [Import guides](../import/README.md)
- [API reference](../api/README.md)
- Examples: [validate-catalog.php](../../examples/validate-catalog.php), [validate-feed.php](../../examples/validate-feed.php)
