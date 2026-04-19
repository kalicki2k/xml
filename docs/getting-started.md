# Getting Started

`kalle/xml` is a compact, strict XML library for PHP 8.2+. The package stays
intentionally narrow in scope: build XML, stream XML output, read XML
incrementally, load XML into a read-only tree when needed, run small
reader-side queries, use explicit DOM interop when needed, import reader
results back into the writer model, canonicalize XML deterministically when
needed, and validate XML against XSD.

This guide gives first-time users a practical path through those pieces.

## 1. Install

```bash
composer require kalle/xml
```

Runtime requirements: `ext-dom`, `ext-libxml`, and `ext-xmlreader`.

## 2. Choose the Right API

- Use `XmlBuilder` when you want to build an immutable XML tree in memory.
- Use `XmlWriter` when you already have a built document and need a string, file, or stream.
- Use `StreamingXmlWriter` when output should be generated incrementally or written straight to a file path or PHP stream.
- Use `StreamingXmlReader` when input is large or incremental and you only need a cursor plus occasional subtree extraction.
- Use `XmlReader` when you want read-only access to an already loaded XML tree.
- Use `XmlCanonicalizer` when XML needs one stable canonical string representation.
- Use DOM interop when you need to connect writer-side or reader-side XML flows to native `DOMDocument` or `DOMElement` values.
- Use `findAll()` and `findFirst()` when filtered element lookups are clearer than repeated traversal.
- Use `XmlImporter` when loaded or queried XML should move back into the writer-side model.
- Use `XmlValidator` when well-formed XML is not enough and the document must match an XSD schema.

These stay separate on purpose. `kalle/xml` is not trying to become a broad
DOM, XPath, or schema framework.

On the writer side, the rule is simple: `XmlBuilder` builds, `XmlWriter`
serializes complete documents, and `StreamingXmlWriter` emits XML
incrementally.

## 3. Write Your First Document with `XmlBuilder`

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\XmlWriter;

echo XmlWriter::toString(XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(XmlBuilder::element('title')->text('Clean Code')),
        ),
));
```

`XmlBuilder` builds immutable writer-side objects. `XmlWriter` serializes the
result through `toString()`, `toFile()`, and `toStream()`. The document model
itself stays separate from serialization.

## 4. Write Your First Streaming Example

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\StreamingXmlWriter;

$stream = fopen('php://stdout', 'wb');

if (!is_resource($stream)) {
    throw new RuntimeException('Could not open stdout for XML output.');
}

$writer = StreamingXmlWriter::forStream($stream);

$writer
    ->startDocument()
    ->startElement('catalog')
    ->startElement('book')
    ->writeAttribute('isbn', '9780132350884')
    ->writeElement(XmlBuilder::element('title')->text('Clean Code'))
    ->endElement()
    ->endElement()
    ->finish();
```

Use streaming when the full tree should not stay in memory or when output
should go directly to a file path or PHP stream. Use `XmlWriter` instead when
you already have a complete `XmlDocument`.

## 5. Read Large XML with `StreamingXmlReader`

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\StreamingXmlReader;

$path = '/path/to/catalog.xml';
$reader = StreamingXmlReader::fromFile($path);

foreach ($reader->readElements('book') as $bookRecord) {
    echo $bookRecord->attributeValue('isbn') . "\n";
    echo $bookRecord->toReaderElement()->firstChildElement('title')?->text() . "\n";
}
```

Use `StreamingXmlReader` when a full in-memory tree would be wasteful. The
cursor stays small, `readElements()` covers common record-by-record workflows,
and `expandElement()` plus `extractElementXml()` remain available when the
lower-level cursor is clearer.

`readElements()` is intentionally non-overlapping: if one yielded `<book>`
contains nested `<book>` elements, those nested matches stay inside the yielded
record instead of being yielded separately.

## 6. Load Your First Document with `XmlReader`

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

## 7. Run Your First Reader Query

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

## 8. Import Your First Reader Result

Continuing from the query example:

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Writer\XmlWriter;

if ($entry !== null) {
    $writerElement = XmlImporter::element($entry)->attribute('exported', true);

    echo XmlWriter::toString(
        XmlBuilder::document($writerElement)->withoutDeclaration(),
    ) . "\n";
}
```

Use import when queried or traversed XML needs to be rewritten, streamed, or
validated through the writer-side model.

## 9. Validate Your First Document

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Validation\XmlValidator;

$catalogDocument = XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(XmlBuilder::element('title')->text('Clean Code')),
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

## 10. Canonicalize XML When Stable Output Matters

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Canonicalization\XmlCanonicalizer;

echo XmlCanonicalizer::document($catalogDocument) . "\n";
```

Use canonicalization when one deterministic XML string should be used for
snapshots, comparison, hashing, or deduplication. The canonicalization surface
stays intentionally small and separate from querying, validation, and DOM
interop.

## 11. Keep Namespace Handling Simple

- Use `XmlBuilder::qname()` when building namespaced elements or attributes.
- Use `QualifiedName` when a reader lookup must match a specific namespace URI.
- Default namespaces apply to elements, not attributes.
- Namespaced attributes need an explicit prefix.
- XPath does not apply the XML default namespace automatically, so queries need an explicit alias such as `['feed' => 'urn:feed']`.
- `XmlImporter` preserves namespace-aware names and rebuilds root-level namespace declarations from imported subtrees.

## 12. Where To Go Next

- Continue with [Writer guides](writer/README.md) for tree-based and streaming output.
- Continue with [Reader guides](reader/README.md) for streaming, traversal, and query details.
- Continue with the [Canonicalization guide](canonicalization/README.md) when XML should normalize to one canonical output.
- Continue with the [DOM interop guide](dom/interop.md) when you need native DOM in the middle of a workflow.
- Continue with [Import guides](import/README.md) for reader-to-writer workflows.
- Continue with [Validation guides](validation/README.md) for schema-focused validation details.
- Continue with [Choosing an API](concepts/choosing-an-api.md) and [Work with Namespaces](concepts/namespaces.md) for the main cross-cutting concepts.
- Continue with [API reference](api/README.md) when you need the class and method-level surface.
- Browse the runnable [examples](../examples/README.md).
