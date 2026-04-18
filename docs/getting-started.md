# Getting Started

`kalle/xml` is a compact, strict XML library for PHP 8.2+. The package stays
intentionally narrow in scope: build XML, stream XML, load XML read-only, run
small reader-side queries, use explicit DOM interop when needed, import reader
results back into the writer model, and validate XML against XSD.

This guide gives first-time users a practical path through those pieces.

## 1. Install

```bash
composer require kalle/xml
```

Runtime requirements: `ext-dom` and `ext-libxml`.

## 2. Choose the Right API

- Use `Xml` when you want to build an XML tree in memory.
- Use `StreamingXmlWriter` when output should be generated incrementally or written straight to a file or stream.
- Use `XmlReader` when you want read-only access to existing XML.
- Use DOM interop when you need to connect writer-side or reader-side XML flows to native `DOMDocument` or `DOMElement` values.
- Use `findAll()` and `findFirst()` when filtered element lookups are clearer than repeated traversal.
- Use `XmlImporter` when loaded or queried XML should move back into the writer-side model.
- Use `XmlValidator` when well-formed XML is not enough and the document must match an XSD schema.

These stay separate on purpose. `kalle/xml` is not trying to become a broad
DOM, XPath, or schema framework.

## 3. Write Your First Document with `Xml`

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;

echo Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(Xml::element('title')->text('Clean Code')),
        ),
)->toString();
```

`Xml` builds immutable writer-side objects. When you need output as something
other than a string, `XmlDocument` also exposes `saveToFile()` and
`saveToStream()`.

## 4. Write Your First Streaming Example

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\StreamingXmlWriter;

$writer = StreamingXmlWriter::forString();

$writer
    ->startDocument()
    ->startElement('catalog')
    ->startElement('book')
    ->writeAttribute('isbn', '9780132350884')
    ->writeElement(Xml::element('title')->text('Clean Code'))
    ->endElement()
    ->endElement()
    ->finish();

echo $writer->toString();
```

Use streaming when the full tree should not stay in memory or when output
should go directly to a file path or PHP stream.

## 5. Load Your First Document with `XmlReader`

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<catalog><book isbn="9780132350884"><title>Clean Code</title></book></catalog>',
);

$book = $document->rootElement()->firstChildElement('book');

if ($book !== null) {
    echo $book->attributeValue('isbn') . "\n";
    echo $book->firstChildElement('title')?->text() . "\n";
}
```

`XmlReader` is read-only. Use it for traversal and inspection, not mutation.

## 6. Run Your First Reader Query

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed"><entry sku="item-1002"><title>Notebook set</title></entry></feed>',
);

$queryNamespaces = [
    'feed' => 'urn:feed',
];

$entry = $document->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', $queryNamespaces);

if ($entry !== null) {
    echo $entry->findFirst('./feed:title', $queryNamespaces)?->text() . "\n";
}
```

The query layer is intentionally small. Queries return `ReaderElement`
instances so you stay inside the reader model after the lookup.

## 7. Import Your First Reader Result

Continuing from the query example:

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Import\XmlImporter;

if ($entry !== null) {
    $writerElement = XmlImporter::element($entry)->attribute('exported', true);

    echo Xml::document($writerElement)->withoutDeclaration()->toString() . "\n";
}
```

Use import when queried or traversed XML needs to be rewritten, streamed, or
validated through the writer-side model.

## 8. Validate Your First Document

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Validation\XmlValidator;

$catalogDocument = Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(Xml::element('title')->text('Clean Code')),
        ),
);

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

$result = $validator->validateXmlDocument($catalogDocument);

if ($result->isValid()) {
    echo "valid\n";
}
```

Invalid but well-formed XML returns a `ValidationResult`. Malformed XML and
invalid schemas still raise exceptions.

## 9. Keep Namespace Handling Simple

- Use `Xml::qname()` when writing namespaced elements or attributes.
- Use `QualifiedName` when a reader lookup must match a specific namespace URI.
- Default namespaces apply to elements, not attributes.
- Namespaced attributes need an explicit prefix.
- XPath does not apply the XML default namespace automatically, so queries need an explicit alias such as `['feed' => 'urn:feed']`.
- `XmlImporter` preserves namespace-aware names and rebuilds root-level namespace declarations from imported subtrees.

## 10. Where To Go Next

- Continue with [Writer guides](writer/README.md) for tree-based and streaming output.
- Continue with [Reader guides](reader/README.md) for traversal and query details.
- Continue with the [DOM interop guide](dom/interop.md) when you need native DOM in the middle of a workflow.
- Continue with [Import guides](import/README.md) for reader-to-writer workflows.
- Continue with [Validation guides](validation/README.md) for schema-focused validation details.
- Continue with [Choosing an API](concepts/choosing-an-api.md) and [Work with Namespaces](concepts/namespaces.md) for the main cross-cutting concepts.
- Continue with [API reference](api/README.md) when you need the class and method-level surface.
- Browse the runnable [examples](../examples/README.md).
