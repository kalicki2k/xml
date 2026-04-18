# Run Reader Queries

The query layer is a small XPath-style convenience on top of `XmlReader`. Use
it when repeated traversal starts getting noisy, but keep it element-oriented.

## What This Is For

Use the query layer when you want:

- filtered descendant lookups without manual traversal chains
- namespace-aware element selection on `ReaderDocument` or `ReaderElement`
- query results that stay inside the compact reader model

## When To Use It

Choose `findAll()` or `findFirst()` when traversal is still read-only but
plain `firstChildElement()` chains are getting verbose.

## Query Documents and Elements

`ReaderDocument` and `ReaderElement` both expose `findAll()` and `findFirst()`.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    '<feed xmlns="urn:feed" xmlns:xlink="urn:xlink"><entry xlink:href="https://example.com/items/1"><title>Blue mug</title></entry></feed>',
);

$queryNamespaces = [
    'feed' => 'urn:feed',
    'xlink' => 'urn:xlink',
];

$entry = $document->findFirst('/feed:feed/feed:entry[@xlink:href]', $queryNamespaces);

if ($entry !== null) {
    echo $entry->findFirst('./feed:title', $queryNamespaces)?->text() . "\n";
}
```

Use absolute expressions on `ReaderDocument` and relative expressions such as
`./feed:title` on `ReaderElement`.

## Namespace Rules

- Prefixed namespaces already in scope on the query context are registered automatically.
- XML default namespaces are not applied automatically by XPath. Map them to an explicit alias such as `['feed' => 'urn:feed']`.
- Pass the `$namespaces` argument when the query needs aliases that are not already in scope.

## What Queries Return

- `findAll()` returns `list<ReaderElement>`.
- `findFirst()` returns `?ReaderElement`.
- Queries must select elements. Use the returned `ReaderElement` for attribute access, text extraction, or more traversal.
- Attribute-only or text-only XPath results are outside the intended public query surface and raise `InvalidQueryException`.

## Boundaries

The query layer remains intentionally narrow:

- it is element-oriented rather than a general XPath result API
- it does not expose a broad XPath wrapper surface
- it complements `XmlReader`; it does not replace traversal entirely
- if a query result needs rewriting or serialization, import it with `XmlImporter`

## Related

- [Reader guides](README.md)
- [Traverse XML with `XmlReader`](traversal.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [Import guides](../import/README.md)
- [API reference](../api/README.md)
- Examples: [query-feed.php](../../examples/query-feed.php), [query-invoice.php](../../examples/query-invoice.php)
