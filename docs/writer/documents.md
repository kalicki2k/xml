# Build Documents with `XmlBuilder`

Use `XmlBuilder` when the document can live in memory and you want a small
immutable model that stays easy to read in code and tests.

## What This Is For

Use the document model when you want to:

- compose an XML tree in memory
- reuse subtrees across multiple documents
- keep tests, fixtures, and example data readable
- serialize complete documents through one consistent writer path

## When To Use It

Choose `XmlBuilder` plus `XmlWriter` over `StreamingXmlWriter` when the whole
document can stay in memory and clarity matters more than incremental
emission.

## Build a Document

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;

$book = XmlBuilder::element('book')
    ->attribute('isbn', '9780132350884')
    ->child(XmlBuilder::element('title')->text('Clean Code'));

$document = XmlBuilder::document(
    XmlBuilder::element('catalog')->child($book),
);
```

`Element` and `XmlDocument` are immutable. Chain calls or reassign the returned
instance when you add attributes, children, or namespace declarations.
`XmlBuilder` only builds that model; `XmlWriter` handles whole-document output.

## Serialize or Save

- `XmlWriter::toString(XmlDocument $document, ?WriterConfig $config = null)` returns XML as a string.
- `XmlWriter::toFile(XmlDocument $document, string $path, ?WriterConfig $config = null)` writes directly to a file.
- `XmlWriter::toStream(XmlDocument $document, mixed $stream, ?WriterConfig $config = null)` writes directly to a PHP stream.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;

echo XmlWriter::toString(
    $document->withoutDeclaration(),
    WriterConfig::pretty(emitDeclaration: false),
);
```

`XmlBuilder::document()` starts with a UTF-8 XML declaration. Use
`withoutDeclaration()` to remove it or `withDeclaration(XmlBuilder::declaration(...))`
to replace it.

## Add Common Node Types

Use the fluent `Element` helpers for common cases:

- `->text('...')`
- `->cdata('...')`
- `->comment('...')`
- `->processingInstruction('target', 'data')`

Use `XmlBuilder::text()`, `XmlBuilder::cdata()`, `XmlBuilder::comment()`, and
`XmlBuilder::processingInstruction()` when you want standalone node objects.

## Namespace-Aware Names

Use `XmlBuilder::qname()` for namespace-aware element and attribute names. For the
full namespace workflow, including default namespaces and namespaced
attributes, continue with [Work with Namespaces](../concepts/namespaces.md).

Raw `prefix:name` strings are rejected on purpose.

## Boundaries

The document model is intentionally small:

- it is immutable rather than mutable
- it is writer-side only; it does not load existing XML
- it does not try to mirror the broader DOM surface
- very large or purely incremental output is usually a better fit for `StreamingXmlWriter`

## Related

- [Writer guides](README.md)
- [Getting Started](../getting-started.md)
- [Stream XML Output](streaming.md)
- [DOM interop guide](../dom/interop.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [Validation guides](../validation/README.md)
- [API reference](../api/README.md)
- Examples: [catalog.php](../../examples/catalog.php), [namespaced-feed.php](../../examples/namespaced-feed.php)
