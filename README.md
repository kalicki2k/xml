# kalle/xml

`kalle/xml` is a strict, writer-focused XML library for PHP 8.2+.

It provides two complementary writing APIs:

- an immutable document model for building and serializing complete XML trees
- `StreamingXmlWriter` for incremental output to strings, files, and streams

The package stays intentionally narrow in scope: XML writing, namespaces,
escaping, and validation. It does not try to become a parser or query library.

## Why kalle/xml

- immutable document model for tree-based XML construction
- explicit namespace-aware names via `Xml::qname()`
- deterministic serialization for tests and reproducible builds
- early XML 1.0 validation for names and character data
- compact and pretty-printed output from the same model
- string, file, and stream output through the same writer path
- `StreamingXmlWriter` for large or incremental output scenarios

## Installation

```bash
composer require kalle/xml
```

See `examples/` for runnable scripts covering the document model, streaming
output, namespace-aware writing, and file targets.

## Choosing an API

Use the document model when you want to compose an XML tree in memory, reuse
subtrees, or keep test fixtures highly readable.

Use `StreamingXmlWriter` when output is generated incrementally, documents are
large, or you want to write directly to a file path or PHP stream without
retaining the full tree in memory.

Both APIs share the same XML rules around escaping, namespace handling, and
writer configuration.

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

The library rejects invalid XML early:

- invalid XML names
- invalid XML 1.0 characters
- invalid comment and processing-instruction content
- namespace declaration conflicts
- unsupported declaration settings such as non-UTF-8 encodings
- invalid streaming writer state transitions

Exception messages are intentionally short and aimed at the call site, so
invalid writer input is easy to diagnose.

## Key Repository Directories

```text
src/
  Attribute/    Attribute value object
  Builder/      Entry-point helpers such as Xml::document()
  Document/     XmlDocument and XmlDeclaration
  Escape/       Escaping and character validation
  Exception/    Library-specific exception types
  Name/         QualifiedName value object
  Namespace/    Namespace declarations and scope handling
  Node/         Element and other writer node types
  Validate/     XML name validation
  Writer/       Streaming writer, output targets, namespace emission, and configuration
tests/
  Unit/         Focused object and validation tests
  Integration/  Document/streaming output, stream/file output, and parser-backed checks
examples/       Runnable examples such as catalog.php, streaming-catalog.php, streaming-to-file.php, and streaming-feed.php
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
php benchmarks/write-performance.php namespace-heavy 25
```

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

Not included:

- parsing
- XPath
- XSD validation
- reader/query APIs
- streaming parser APIs

## Status

v1.1 extends the original document model with production-oriented streaming XML
writing. The package is ready for early public use as a focused XML writer, but
its scope remains intentionally narrow. Near-term releases should keep refining
the writer surface rather than expanding into parser or query features. See
`docs/roadmap.md` for the current milestone summary.
