# kalle/xml

`kalle/xml` is a strict XML library for PHP 8.2+ with tree-based writing,
streaming writing, read-only XML reading, a small namespace-aware
XPath-style query layer on top of the reader API, a compact reader-to-writer
import bridge, and compact XSD validation.

It currently provides:

- `Xml` as the writer-side facade for an immutable document model
- `StreamingXmlWriter` for incremental output to strings, files, and streams
- `XmlReader` for loading and traversing existing XML through a compact
  read-only API
- `findAll()` and `findFirst()` on `ReaderDocument` and `ReaderElement` for
  small namespace-aware element queries
- `XmlImporter` for importing `ReaderDocument` and `ReaderElement` into the
  immutable writer-side document model
- `XmlValidator` for validating XML strings, files, streams, and `XmlDocument`
  instances against XSD schemas

The package stays intentionally narrow in scope: XML writing, namespaces,
escaping, XSD validation, small-scale read-only traversal, small-scale
namespace-aware querying, and small-scale reader-to-writer import workflows. It
does not try to become a full parser, DOM clone, or broad schema/query
framework.

## Why kalle/xml

- `Xml` for tree-based XML construction
- explicit namespace-aware names via `Xml::qname()`
- deterministic serialization for tests and reproducible builds
- early XML 1.0 validation for names and character data
- compact and pretty-printed output from the same model
- string, file, and stream output through the same writer path
- `StreamingXmlWriter` for large or incremental output scenarios
- `XmlReader` for namespace-aware loading from strings, files, and streams
- small XPath-style queries layered on top of the read-only reader model
- `XmlImporter` for practical query-to-write and traversal-to-write workflows
- `XmlValidator` for compact, explicit schema validation with useful
  diagnostics

## Installation

```bash
composer require kalle/xml
```

Runtime requirements: `ext-dom` and `ext-libxml`.

Optional benchmark comparisons use `ext-xmlwriter`.

See `examples/` for runnable scripts covering `Xml`, `StreamingXmlWriter`,
`XmlReader`, reader queries, reader-to-writer import, and XSD validation.

## Choosing an API

- Use `Xml` when you want to compose an XML tree in memory, reuse subtrees, or
  keep fixtures and tests highly readable.
- Use `StreamingXmlWriter` when output is generated incrementally, documents
  are large, or you want to write directly to a file path or PHP stream
  without retaining the full tree in memory.
- Use `XmlReader` traversal when you want to inspect existing XML through a
  small, namespace-aware, read-only API with explicit element and attribute
  access.
- Use reader queries when filtered descendant lookups or namespace-aware
  element selections are clearer than chaining repeated `firstChildElement()`
  calls.
- Use `XmlImporter` when you want to take reader or query results back into
  the immutable writer model for rewriting, filtering, or re-serialization.
- Use `XmlValidator` when XML well-formedness alone is not enough and you want
  explicit XSD validation against schema strings, schema files, schema
  streams, or existing `XmlDocument` instances.

`Xml` and `StreamingXmlWriter` share the same XML rules around escaping,
namespace handling, and writer configuration. `XmlReader` stays separate, its
query layer remains intentionally small rather than exposing the broader
DOM/XPath surface, and `XmlImporter` bridges reader results back into the
writer model without introducing mutation or DOM-clone behavior.
`XmlValidator` is a separate capability again, so schema validation does not
blur writing, reading, querying, and importing together.

## Document Model Quick Start

Build a document and call `toString()`:

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;

echo Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->text('Clean Code'),
        ),
)->toString();
```

Output:

```xml
<?xml version="1.0" encoding="UTF-8"?><catalog><book isbn="9780132350884">Clean Code</book></catalog>
```

Write an existing document directly to a stream resource when you do not want
an intermediate string:

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$stream = fopen('php://output', 'wb');

if ($stream === false) {
    throw new RuntimeException('Unable to open php://output.');
}

Xml::document(
    Xml::element('catalog')
        ->child(Xml::element('book')->attribute('isbn', '9780132350884')),
)->withoutDeclaration()->saveToStream($stream, WriterConfig::pretty(emitDeclaration: false));
```

## Streaming Writer Quick Start

Use `StreamingXmlWriter` when you want to generate XML incrementally without
building the whole document tree first.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$writer = StreamingXmlWriter::forStream(
    fopen('php://output', 'wb'),
    WriterConfig::pretty(),
);

$writer
    ->startDocument()
    ->startElement('catalog')
    ->writeComment('nightly export')
    ->startElement('book')
    ->writeAttribute('isbn', '9780132350884')
    ->startElement('title')
    ->writeText('Clean Code')
    ->endElement()
    ->endElement()
    ->endElement()
    ->finish();
```

Streaming writer notes:

- `forString()` buffers output in memory, `forFile()` writes to a file path, and `forStream()` writes to a PHP stream resource
- `toString()` is available only for `forString()` writers after `finish()`
- namespace declarations are auto-resolved from element and attribute names
- `writeElement()` lets you mix prebuilt immutable subtrees into a stream
- pretty-printed imperative streaming is intended for structural content; use compact mode for unconstrained mixed-content generation

See `examples/streaming-to-file.php` for a minimal `forFile()` example that
also mixes prebuilt elements into a stream.

## Reader Quick Start

Use `XmlReader` when you want to load existing XML from a string, file, or
stream and inspect it through a small read-only API.

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

Reader notes:

- `fromString()`, `fromFile()`, and `fromStream()` keep loading separate from the writer APIs
- `rootElement()`, `firstChildElement()`, `childElements()`, `attributeValue()`, and `text()` cover the common inspection path
- `findAll()` and `findFirst()` add a small XPath-style query layer on top of the read-only model and return `ReaderElement` matches
- element and attribute lookups use the same `QualifiedName` model as the writer side
- queries that select attributes or text directly are outside the intended public query surface; use the returned elements for further traversal and attribute access
- in-scope prefixed namespaces are available to queries automatically; for a document default namespace, map the URI to an explicit alias such as `['feed' => 'urn:feed']` because XPath does not apply XML default namespaces automatically
- namespaces in scope are exposed separately from regular attributes
- the reader stays intentionally small and does not expose the full DOM/XPath surface

See `examples/reading-catalog.php`, `examples/reading-config.php`,
`examples/reading-feed.php`, `examples/reading-stream.php`,
`examples/query-feed.php`, `examples/query-invoice.php`,
`examples/import-feed-entry.php`, and `examples/import-invoice-party.php`
for runnable reader, query, and import examples.

## Reader-To-Writer Import Quick Start

Use `XmlImporter` when a loaded or queried reader subtree should move back
into the immutable writer model.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed"><entry sku="item-1002"><title>Notebook set</title></entry></feed>',
);

$entry = $document->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);

if ($entry !== null) {
    $writerElement = XmlImporter::element($entry)->attribute('exported', true);

    echo Xml::document($writerElement)->withoutDeclaration()->toString() . "\n";
}
```

Import notes:

- `document()` converts a `ReaderDocument` into `XmlDocument`
- `element()` converts a `ReaderElement` subtree into a writer-side `Element`
- imported results are regular immutable writer-side `XmlDocument` and `Element` instances, so they work with `Xml`, `StreamingXmlWriter`, and `XmlValidator`
- element names, attributes, text, comments, CDATA, and processing instructions are preserved through the import bridge
- root-level namespace declarations are rebuilt from the imported subtree so namespace-aware query-to-write workflows stay correct
- imported elements can be routed either into `Xml::document(...)` or straight into `StreamingXmlWriter::writeElement(...)`
- import stays intentionally small; unsupported constructs such as DOCTYPE-backed document import or entity references raise `ImportException`

See `examples/import-feed-entry.php` and `examples/import-invoice-party.php`
for runnable import workflows.

## Reader Query Quick Start

Use the query layer when traversal by repeated `firstChildElement()` calls
starts getting noisy or when you need filtered descendant lookups. The query
API is element-oriented: `findAll()` and `findFirst()` return `ReaderElement`
matches that you continue traversing through the reader model.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed" xmlns:xlink="urn:xlink"><entry xlink:href="https://example.com/items/1"><title>Blue mug</title></entry></feed>',
);

// XPath does not apply the XML default namespace automatically, so map it to
// an explicit query alias.
$queryNamespaces = [
    'feed' => 'urn:feed',
    'xlink' => 'urn:xlink',
];

$entries = $document->findAll('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);
$entry = $entries[0] ?? null;

if ($entry !== null) {
    echo $entry->findFirst('./feed:title', $queryNamespaces)?->text() . "\n";
}
```

## XSD Validation Quick Start

Use `XmlValidator` when you want explicit schema validation with a compact
result object instead of broad schema tooling.

```php
<?php

declare(strict_types=1);

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
                ->child(Xml::element('title')->text('Clean Code')),
        ),
);

$result = $validator->validateXmlDocument($document);

if ($result->isValid()) {
    echo "valid\n";
}
```

Validation notes:

- `fromString()`, `fromFile()`, and `fromStream()` bind the validator to one XSD schema source
- `validateString()`, `validateFile()`, `validateStream()`, and `validateXmlDocument()` all return `ValidationResult`
- malformed XML still throws `ParseException`; unreadable inputs still throw file or stream exceptions
- invalid or unreadable XSD schemas throw `InvalidSchemaException`
- `validateXmlDocument()` lets you validate writer-built documents directly without manually serializing first
- `fromFile()` keeps schema-file validation path-based, so relative `xs:include` and `xs:import` locations continue to work
- invalid-but-well-formed XML returns `ValidationResult::isValid() === false` plus `ValidationError` diagnostics

See `examples/validate-catalog.php` and `examples/validate-feed.php` for
runnable validation examples.

## Namespace-Aware API

The same namespace rules apply to the document model and to
`StreamingXmlWriter`.

Use `Xml::qname()` for namespace-aware element and attribute names. Raw
`prefix:name` strings are rejected on purpose.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element(Xml::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->declareNamespace('media', 'urn:media')
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->child(Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))),
        ),
)->withoutDeclaration();

echo $document->toString(WriterConfig::pretty(emitDeclaration: false));
```

Output:

```xml
<feed xmlns="urn:feed" xmlns:media="urn:media">
    <entry>
        <media:thumbnail/>
    </entry>
</feed>
```

Namespace rules:

- default namespaces apply to elements, not attributes
- use `declareDefaultNamespace()` for explicit default namespace declarations
- use `declareNamespace()` for explicit prefixed declarations
- required namespaces are declared automatically when missing from scope
- namespace declarations are serialized before normal attributes

## Escaping and Validation

Escaping happens during serialization or streaming emission, not while building
the object graph. The model stores raw values, validates them early, and
renders them deterministically.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element('payload')
        ->comment('raw script fragment follows')
        ->child(
            Xml::element('script')
                ->child(Xml::cdata('if (a < b && c > d) { return "ok"; }')),
        )
        ->processingInstruction('render-cache', 'ttl="300"')
        ->child(Xml::element('status')->text('ok')),
)->withoutDeclaration();

echo $document->toString(WriterConfig::pretty(emitDeclaration: false));
```

Output:

```xml
<payload>
    <!--raw script fragment follows-->
    <script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script>
    <?render-cache ttl="300"?>
    <status>ok</status>
</payload>
```

## Validation and Errors

The library rejects invalid XML and invalid XSD usage early:

- invalid XML names
- invalid XML 1.0 characters
- invalid comment and processing-instruction content
- namespace declaration conflicts
- unsupported declaration settings such as non-UTF-8 encodings
- invalid streaming writer state transitions
- malformed XML inputs passed to readers or validators
- invalid or unreadable XSD schemas

Exception messages are intentionally short and aimed at the call site, so
invalid writer, reader, query, and validation input is easy to diagnose.

## Key Repository Directories

```text
src/
  Attribute/    Attribute value object
  Builder/      Entry-point helpers such as Xml::document()
  Document/     XmlDocument and XmlDeclaration
  Escape/       Escaping and character validation
  Exception/    Library-specific exception types
  Import/       Reader-to-writer import bridge
  Name/         QualifiedName value object
  Namespace/    Namespace declarations and scope handling
  Node/         Element and other writer node types
  Reader/       Read-only traversal plus small XPath-style reader queries
  Validation/   XSD validation and validation result types
  Validate/     XML name validation
  Writer/       Streaming writer, output targets, namespace emission, and configuration
tests/
  Unit/         Focused object and validation tests
  Integration/  Document/streaming output, reader traversal, reader queries, reader import, XSD validation, stream/file output, and parser-backed checks
examples/       Runnable examples such as catalog.php, query-feed.php, import-feed-entry.php, import-invoice-party.php, validate-catalog.php, validate-feed.php, and streaming-feed.php
benchmarks/     Maintained performance comparison fixtures
docs/           Maintainer-facing notes
```

## Development

Common development commands:

```bash
composer test
composer stan
composer cs-check
composer cs-fix
composer qa
```

Benchmarking:

```bash
php benchmarks/write-performance.php
php benchmarks/write-performance.php medium
php benchmarks/write-performance.php namespace-heavy 25
php benchmarks/write-performance.php 50
php benchmarks/document-vs-streaming.php 5000 15
```

See `benchmarks/README.md` for the measured implementations, interpretation
guidance, and benchmark limitations.

The integration suite uses `DOMDocument` to verify that writer output is
well-formed, not just string snapshots.

## Scope

Included today:

- XML documents and declarations
- elements, text, comments, CDATA, and processing instructions
- namespace-aware names and namespace declarations
- deterministic compact and pretty-printed serialization
- file and stream output with library-specific write exceptions
- imperative streaming XML writing for writer-heavy workloads
- read-only document and element traversal via `XmlReader`
- a small XPath-style query layer on top of the reader model
- reader-to-writer import via `XmlImporter`
- XSD validation for XML strings, files, streams, and `XmlDocument`

Still out of scope:

- mutation APIs for queried or loaded XML
- broad DOM/XPath wrapper APIs beyond `findFirst()` and `findAll()`
- broad schema-framework features beyond compact XSD validation
- XML-to-array or XML-to-object mapping
- streaming parser APIs

## Status

v1.5 adds a compact reader-to-writer import bridge alongside the existing
writer, reader, query, and validation APIs. The package remains ready for
early public use as a focused XML tool, but its scope is still intentionally
narrow. Near-term releases should keep refining the writer, reader, query,
import, and validation surfaces rather than expanding into mutation, mapping,
or broad XML frameworks. See `docs/roadmap.md` for the current milestone
summary.
