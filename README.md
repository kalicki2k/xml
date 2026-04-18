# kalle/xml

`kalle/xml` is a compact, strict XML library for PHP 8.2+.

It provides:

- `Xml` for immutable, tree-based XML construction
- `StreamingXmlWriter` for incremental XML output to strings, files, and streams
- `XmlReader` for read-only traversal of existing XML
- `XmlDomBridge` plus DOM entry points on `XmlReader` for explicit DOM interop
- `findAll()` and `findFirst()` for small namespace-aware element queries on the reader model
- `XmlImporter` for importing reader results back into the writer model
- `XmlValidator` for validating XML against XSD schemas

The package stays intentionally narrow in scope. It covers XML writing,
read-only loading, small reader-side queries, explicit DOM interop,
reader-to-writer import, and XSD validation without trying to replace DOM,
wrap all of XPath, or become a broad XML framework.

## Installation

```bash
composer require kalle/xml
```

Runtime requirements: `ext-dom` and `ext-libxml`.

## Current Scope

Included:

- tree-based XML writing with `Xml`
- streaming XML writing with `StreamingXmlWriter`
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

## When To Use Each API

- Use `Xml` when you want to build an XML tree in memory, reuse subtrees, or keep fixtures readable.
- Use `StreamingXmlWriter` when output is incremental, large, or should go straight to a file or stream.
- Use `XmlReader` when you need read-only traversal of existing XML.
- Use DOM interop when writer-side or reader-side flows need to connect to existing `DOMDocument` or `DOMElement` values without adopting a mutable DOM wrapper.
- Use reader queries when `findAll()` or `findFirst()` is clearer than repeated traversal.
- Use `XmlImporter` when reader results need to move back into the writer-side model.
- Use `XmlValidator` when XML must match an XSD schema.

## Documentation

- [Documentation index](docs/README.md)
- [Getting Started](docs/getting-started.md)
- [Writer guides](docs/writer/README.md)
- [Reader guides](docs/reader/README.md)
- [DOM interop guide](docs/dom/interop.md)
- [Import guides](docs/import/README.md)
- [Validation guides](docs/validation/README.md)
- [Choosing an API](docs/concepts/choosing-an-api.md)
- [Work with Namespaces](docs/concepts/namespaces.md)
- [API reference](docs/api/README.md)
- [Examples](examples/README.md)
- DOM examples: [dom-roundtrip.php](examples/dom-roundtrip.php), [dom-feed-query.php](examples/dom-feed-query.php), [dom-invoice-stream.php](examples/dom-invoice-stream.php)
