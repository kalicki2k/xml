# Build Documents with `Xml`

Use `Xml` when the document can live in memory and you want a small immutable
model that stays easy to read in code and tests.

## What This Is For

Use the document model when you want to:

- compose an XML tree in memory
- reuse subtrees across multiple documents
- keep tests, fixtures, and example data readable
- serialize through one consistent writer path

## When To Use It

Choose `Xml` over `StreamingXmlWriter` when the whole document can stay in
memory and clarity matters more than incremental emission.

## Build a Document

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;

$book = Xml::element('book')
    ->attribute('isbn', '9780132350884')
    ->child(Xml::element('title')->text('Clean Code'));

$document = Xml::document(
    Xml::element('catalog')->child($book),
);
```

`Element` and `XmlDocument` are immutable. Chain calls or reassign the returned
instance when you add attributes, children, or namespace declarations.

## Serialize or Save

- `XmlDocument::toString(?WriterConfig $config = null)` returns XML as a string.
- `XmlDocument::saveToFile(string $path, ?WriterConfig $config = null)` writes directly to a file.
- `XmlDocument::saveToStream(mixed $stream, ?WriterConfig $config = null)` writes directly to a PHP stream.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Writer\WriterConfig;

echo $document->withoutDeclaration()->toString(
    WriterConfig::pretty(emitDeclaration: false),
);
```

`Xml::document()` starts with a UTF-8 XML declaration. Use
`withoutDeclaration()` to remove it or `withDeclaration(Xml::declaration(...))`
to replace it.

## Add Common Node Types

Use the fluent `Element` helpers for common cases:

- `->text('...')`
- `->cdata('...')`
- `->comment('...')`
- `->processingInstruction('target', 'data')`

Use `Xml::text()`, `Xml::cdata()`, `Xml::comment()`, and
`Xml::processingInstruction()` when you want standalone node objects.

## Namespace-Aware Names

Use `Xml::qname()` for namespace-aware element and attribute names. For the
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
