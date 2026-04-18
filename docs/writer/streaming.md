# Stream XML Output

Use `StreamingXmlWriter` when XML should be produced incrementally or written
straight to a string buffer, file path, or PHP stream.

## What This Is For

Use the streaming writer when you want to:

- emit XML as you go instead of building a full tree first
- write directly to a file path or PHP stream
- combine incremental output with prebuilt `Element` subtrees

## When To Use It

Choose `StreamingXmlWriter` over `Xml` when output is large, incremental, or
best written directly to its destination.

## Choose an Output Target

- `StreamingXmlWriter::forString()` buffers output in memory and exposes `toString()` after `finish()`.
- `StreamingXmlWriter::forFile($path)` writes directly to a file path.
- `StreamingXmlWriter::forStream($stream, ?WriterConfig $config = null, bool $closeOnFinish = false)` writes to a PHP stream resource.

If you already have an `XmlDocument`, `writeDocument()` sends it through the
same output path.

## Write Incrementally

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$writer = StreamingXmlWriter::forString(WriterConfig::pretty());

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

Call `startDocument()` when you want an XML declaration in a manual streaming
flow. `finish()` is required before `toString()` or before considering the
output complete.

## Mix Streaming with Prebuilt Subtrees

`writeElement()` accepts a regular writer-side `Element`. Use it to reuse
subtrees built with `Xml` or imported with `XmlImporter`.

```php
$writer->writeElement(
    Xml::element('price')
        ->attribute('currency', 'EUR')
        ->text('39.90'),
);
```

## Namespaces and Pretty Printing

- Use `declareNamespace()` and `declareDefaultNamespace()` before writing content for the current element.
- Required namespace declarations are emitted automatically from namespaced elements and attributes.
- Pretty-printed streaming is intended for structural content. In pretty mode, you cannot add text or CDATA after structural children in the same element.
- Use `WriterConfig::compact()` for unconstrained mixed-content generation, or build that subtree with `Xml` and stream it with `writeElement()`.

For the full namespace rules, continue with
[Work with Namespaces](../concepts/namespaces.md).

## Boundaries

The streaming writer is intentionally strict:

- it enforces element open/close order explicitly
- `toString()` is only available for `forString()` writers after `finish()`
- it is not a streaming parser
- if you want an in-memory tree first, `Xml` is usually simpler

## Related

- [Writer guides](README.md)
- [Getting Started](../getting-started.md)
- [Build Documents with `Xml`](documents.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [Import guides](../import/README.md)
- [API reference](../api/README.md)
- Examples: [streaming-catalog.php](../../examples/streaming-catalog.php), [streaming-feed.php](../../examples/streaming-feed.php), [streaming-to-file.php](../../examples/streaming-to-file.php)
