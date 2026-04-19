# Read Large XML with `StreamingXmlReader`

Use `StreamingXmlReader` when XML input is large, incremental, or already
arrives through a file or stream and a cursor is enough.

## What This Is For

Use this capability when you want to:

- walk through XML incrementally instead of loading a full document tree
- iterate non-overlapping record elements with `readElements()`
- inspect namespace-aware element names and attributes at the current cursor position
- extract one matching subtree into the existing reader, importer, validator, or writer flows

## When To Use It

Choose `StreamingXmlReader` when:

- input is large enough that loading the full document would be wasteful
- processing is record-oriented, such as one `<entry>`, `<book>`, or invoice subtree at a time
- you only need cursor-style inspection plus occasional subtree extraction

Stay with `XmlReader` when you want document-wide traversal, repeated random
access, or direct document- and element-scoped queries.

## Process Matching Records with `readElements()`

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

`readElements()` yields non-overlapping matching elements as `StreamedElement`
records. If one yielded `<book>` contains nested `<book>` elements, those
nested matches stay inside that yielded record and are not yielded separately.
It keeps common attribute access small, while `toReaderElement()`,
`toXmlString()`, `validate()`, and `toWriterElement()` bridge back into the regular reader,
validation, import, and writer flows.

Use the lower-level `read()` loop instead when you need every node, want to
inspect text or comments directly, or intentionally want nested matching
elements inside a yielded record subtree. Keep each workflow in one style:
use `readElements()` for record loops, or `read()` for node-level cursor work.

## Read Incrementally from a Stream

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\StreamingXmlReader;

$reader = StreamingXmlReader::fromStream($stream);

while ($reader->read()) {
    if (!$reader->isStartElement(XmlBuilder::qname('entry', 'urn:feed'))) {
        continue;
    }

    $entry = $reader->expandElement();

    echo XmlImporter::element($entry)->name() . "\n";
}
```

## Current Node State

The cursor API stays intentionally small:

- `read()` advances to the next node and returns `false` at normal end of input
- `isOpen()` and `hasCurrentNode()` make the cursor lifecycle explicit
- `nodeType()` returns a `StreamingNodeType`
- `name()`, `localName()`, `prefix()`, and `namespaceUri()` describe the current node
- `isText()`, `isComment()`, and `isCdata()` give small node-type shortcuts for common content loops
- `attributes()`, `attribute()`, and `attributeValue()` read current-element attributes
- `isStartElement()` and `isEndElement()` make element-oriented loops clearer

Before the first successful `read()`, after normal end of input, or after
`close()`, current-node accessors return `null` and element helpers fall back
to `false`, `null`, or `[]`.

Namespace and attribute rules stay aligned with the rest of the package:

- default namespaces apply to elements
- plain attributes stay unnamespaced
- namespaced attribute lookups should use `QualifiedName`

## Expand One Matching Subtree

Two extraction paths cover the practical workflows:

- `expandElement()` materializes the current start element as a regular `ReaderElement`
- `extractElementXml()` returns the current start element subtree as an XML string without a declaration

That lets you keep using:

- `findFirst()` / `findAll()` on that expanded subtree
- `XmlImporter::element()` to move back into the writer model
- `XmlReader::fromString()` when a full subtree document context is clearer
- `XmlValidator::validateString()` when selected records need schema validation
- `XmlWriter::toString(XmlBuilder::document(...))` or `StreamingXmlWriter` for output

Subtree extraction does not advance the cursor. After `expandElement()` or
`extractElementXml()`, the reader is still positioned on the same start
element. The next `read()` continues normal streaming traversal from there.

## Filter, Validate, and Re-Emit Matching Records

One common workflow is to stream through a large file, keep the cursor small,
and only import the matching records that should be written again.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Reader\StreamingXmlReader;
use Kalle\Xml\Validation\XmlValidator;
use Kalle\Xml\Writer\StreamingXmlWriter;

$reader = StreamingXmlReader::fromFile('/path/to/feed.xml');
$validator = XmlValidator::fromString($entrySchema);
$output = fopen('php://stdout', 'wb');

if (!is_resource($output)) {
    throw new RuntimeException('Could not open stdout for XML output.');
}

$writer = StreamingXmlWriter::forStream($output);

$writer->startElement('selection');

foreach ($reader->readElements(XmlBuilder::qname('entry', 'urn:feed')) as $entryRecord) {
    if ($entryRecord->attributeValue('sku') === 'item-1002') {
        continue;
    }

    if (!$entryRecord->validate($validator)->isValid()) {
        continue;
    }

    $writer->writeElement($entryRecord->toWriterElement());
}

$writer->endElement()->finish();
```

For cursor-driven workflows, the same package pieces still compose cleanly:

- `toReaderElement()` returns a regular `ReaderElement` for traversal and `findFirst()` / `findAll()`
- `toXmlString()` returns the selected subtree as XML without a declaration
- `validate()` is a thin shorthand for validating `toXmlString()` with `XmlValidator`
- `toWriterElement()` reuses `XmlImporter` so `XmlWriter` and `StreamingXmlWriter` can re-emit selected records

## Boundaries

`StreamingXmlReader` stays intentionally narrow:

- it is a cursor, not a second full reader tree model
- `readElements()` is record-oriented iteration, not a broad query or event framework
- `StreamedElement` is a small record snapshot, not a second traversal or transformation API
- it does not add broad query support directly on the streaming cursor
- it does not mutate loaded XML
- document-wide traversal and DOM-backed loading still belong to `XmlReader`

## Related

- [Traverse XML with `XmlReader`](traversal.md)
- [Run Reader Queries](queries.md)
- [Import guides](../import/README.md)
- [Streaming reader API](../api/streaming-reader.md)
- Examples: [streaming-reader-catalog.php](../../examples/streaming-reader-catalog.php), [streaming-reader-invoice.php](../../examples/streaming-reader-invoice.php), [streaming-reader-feed-export.php](../../examples/streaming-reader-feed-export.php)
