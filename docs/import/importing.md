# Import Reader Results

Use `XmlImporter` when a loaded document or query result needs to move back
into the writer-side model.

## What This Is For

Use import when you want to:

- take a `ReaderDocument` back into `XmlDocument`
- turn a `ReaderElement` subtree into a writer-side `Element`
- bridge reader workflows back into writing, streaming, or validation

## When To Use It

Choose `XmlImporter` when traversal or querying is finished and the result now
needs writer-side operations.

## Import a Document or an Element

- `XmlImporter::document(ReaderDocument $document)` returns `XmlDocument`.
- `XmlImporter::element(ReaderElement $element)` returns `Element`.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\XmlWriter;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed"><entry sku="item-1002"><title>Notebook set</title></entry></feed>',
);

$entry = $document->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);

if ($entry !== null) {
    $writerElement = XmlImporter::element($entry)->attribute('exported', true);

    echo XmlWriter::toString(
        XmlBuilder::document($writerElement)->withoutDeclaration(),
    ) . "\n";
}
```

## What Import Preserves

- element names and namespace URIs
- attributes
- text nodes, comments, CDATA, and processing instructions
- root-level namespace declarations rebuilt from the imported subtree

Imported results are regular `Element` and `XmlDocument` instances. They work
with `XmlBuilder`, `XmlWriter`, `StreamingXmlWriter`, and `XmlValidator`.
Build a new document with `XmlBuilder`, serialize it with `XmlWriter`, or
stream imported subtrees incrementally with `StreamingXmlWriter`.

## Boundaries

`XmlImporter` is intentionally small. It throws `ImportException` for
unsupported cases such as:

- document-level comments
- document-level processing instructions
- DOCTYPE declarations
- entity references

It also does not add a mutable bridge between reader and writer objects; it
produces regular immutable writer-side values.

## Related

- [Import guides](README.md)
- [Reader guides](../reader/README.md)
- [Run Reader Queries](../reader/queries.md)
- [Writer guides](../writer/README.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [API reference](../api/README.md)
- Examples: [import-feed-entry.php](../../examples/import-feed-entry.php), [import-invoice-party.php](../../examples/import-invoice-party.php)
