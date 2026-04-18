# Traverse XML with `XmlReader`

Use `XmlReader` when you need a small, read-only view of XML loaded from a
string, file, or stream.

## What This Is For

Use the reader when you want to:

- load existing XML from a string, file, or stream
- inspect elements, attributes, and text through a compact API
- keep parsing separate from writer-side document construction

## When To Use It

Choose `XmlReader` when you need read-only access to existing XML and a full
DOM-style API would be more surface than you want.

## Load XML

- `XmlReader::fromString(string $xml)`
- `XmlReader::fromFile(string $path)`
- `XmlReader::fromStream(mixed $stream)`

All three return `ReaderDocument`.

## Traverse Elements and Attributes

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<catalog><book isbn="9780132350884"><title>Clean Code</title></book></catalog>',
);

$root = $document->rootElement();
$book = $root->firstChildElement('book');

if ($book !== null) {
    echo $book->name() . "\n";
    echo $book->attributeValue('isbn') . "\n";
    echo $book->firstChildElement('title')?->text() . "\n";
}
```

Common traversal methods:

- `rootElement()`
- `childElements(?string|QualifiedName $name = null)`
- `firstChildElement(?string|QualifiedName $name = null)`
- `parent()`
- `attributes()`, `attribute()`, `attributeValue()`, `hasAttribute()`
- `text()`

## Namespace-Aware Lookups

Use `QualifiedName` when you need an exact namespace-aware lookup.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed" xmlns:xlink="urn:xlink"><entry xlink:href="https://example.com/items/1"/></feed>',
);

$entryName = new QualifiedName('entry', 'urn:feed');
$hrefName = new QualifiedName('href', 'urn:xlink', 'xlink');

$entry = $document->rootElement()->firstChildElement($entryName);
$href = $entry?->attributeValue($hrefName);
```

`ReaderElement::namespacesInScope()` exposes namespace declarations separately
from regular attributes.

For the cross-cutting namespace rules, continue with
[Work with Namespaces](../concepts/namespaces.md).

## Boundaries

The reader API stays intentionally small:

- it does not mutate loaded XML
- it does not expose the broader DOM surface
- filtered element lookups belong in the query layer
- if loaded XML needs to move back into the writer model, use `XmlImporter`

## Related

- [Reader guides](README.md)
- [Getting Started](../getting-started.md)
- [Run Reader Queries](queries.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [Import guides](../import/README.md)
- [API reference](../api/README.md)
- Examples: [reading-catalog.php](../../examples/reading-catalog.php), [reading-config.php](../../examples/reading-config.php), [reading-feed.php](../../examples/reading-feed.php), [reading-stream.php](../../examples/reading-stream.php)
