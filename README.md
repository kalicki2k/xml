# kalle/xml

`kalle/xml` is a compact, strict XML library for PHP 8.2+.

`XmlBuilder` builds immutable XML models. `XmlWriter` serializes complete
`XmlDocument` values. `StreamingXmlWriter` is the separate imperative writer
for incremental output to file and stream targets.

It provides:

- `XmlBuilder` for immutable, tree-based XML construction
- `XmlWriter` for serializing built documents to strings, files, or streams
- `StreamingXmlWriter` for incremental XML output to files and streams
- `StreamingXmlReader` for incremental, cursor-based XML reading from files and streams
- `XmlReader` for read-only traversal of existing XML
- `XmlDomBridge` plus DOM entry points on `XmlReader` for explicit DOM interop
- `findAll()` and `findFirst()` for small namespace-aware element queries on the reader model
- `XmlImporter` for importing reader results back into the writer model
- `XmlValidator` for validating XML against XSD schemas

The package stays intentionally narrow in scope. It covers XML writing,
streaming XML reading, read-only tree loading, small reader-side queries,
explicit DOM interop, reader-to-writer import, and XSD validation without
trying to replace DOM, wrap all of XPath, or become a broad XML framework.

## Installation

```bash
composer require kalle/xml
```

Runtime requirements: `ext-dom`, `ext-libxml`, and `ext-xmlreader`.

## Current Scope

Included:

- tree-based XML building with `XmlBuilder`
- document serialization with `XmlWriter::toString()`, `toFile()`, and `toStream()`
- streaming XML writing with `StreamingXmlWriter`
- streaming XML reading with `StreamingXmlReader`
- read-only XML loading with `XmlReader`
- explicit DOM interop with `XmlDomBridge` and `XmlReader::fromDomDocument()` / `fromDomElement()`
- small namespace-aware element queries with `findAll()` and `findFirst()`
- reader-to-writer import with `XmlImporter`
- compact XSD validation with `XmlValidator`

Out of scope:

- mutation APIs for loaded XML
- broad DOM or XPath wrapper APIs
- XML-to-array or XML-to-object mapping
- broad schema-framework features beyond compact XSD validation

## Quick Example

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('isbn', '9780132350884')
                ->child(XmlBuilder::element('title')->text('Clean Code')),
        ),
);

echo XmlWriter::toString($document);
```

## When To Use Each API

- Use `XmlBuilder` when you want to build an immutable XML tree in memory, reuse subtrees, or keep fixtures readable.
- Use `XmlWriter` when you already have a built `XmlDocument` and want a string, file, or stream.
- Use `StreamingXmlWriter` when output is incremental, large, or should go straight to a file or stream.
- Use `StreamingXmlReader` when input is large or incremental and you only need cursor-style inspection, subtree extraction, or filtered export.
- Use `XmlReader` when you want a loaded tree for traversal, parent/child navigation, or queries.
- Use DOM interop when writer-side or reader-side flows need to connect to existing `DOMDocument` or `DOMElement` values without adopting a mutable DOM wrapper.
- Use reader queries when `findAll()` or `findFirst()` is clearer than repeated traversal.
- Use `XmlImporter` when reader results need to move back into the writer-side model.
- Use `XmlValidator` when XML must match an XSD schema.

That split is intentional: building, whole-document serialization, and
incremental streaming stay separate so the package stays compact.

## Documentation

- [Documentation index](docs/README.md)
- [Getting Started](docs/getting-started.md)
- [Writer guides](docs/writer/README.md)
- [Reader guides](docs/reader/README.md)
- [Streaming reader guide](docs/reader/streaming.md)
- [DOM interop guide](docs/dom/interop.md)
- [Import guides](docs/import/README.md)
- [Validation guides](docs/validation/README.md)
- [Choosing an API](docs/concepts/choosing-an-api.md)
- [Work with Namespaces](docs/concepts/namespaces.md)
- [API reference](docs/api/README.md)
- [Examples](examples/README.md)
- Streaming reader examples: [streaming-reader-catalog.php](examples/streaming-reader-catalog.php), [streaming-reader-invoice.php](examples/streaming-reader-invoice.php), [streaming-reader-feed-export.php](examples/streaming-reader-feed-export.php)
- DOM examples: [dom-roundtrip.php](examples/dom-roundtrip.php), [dom-feed-query.php](examples/dom-feed-query.php), [dom-invoice-stream.php](examples/dom-invoice-stream.php)
