# Query API

The query API is a small XPath-style layer on top of the reader model.

## Where Queries Live

Queries are available on both:

- `ReaderDocument`
- `ReaderElement`

The public methods are the same in both places:

- `findAll(string $expression, array $namespaces = []): list<ReaderElement>`
- `findFirst(string $expression, array $namespaces = []): ?ReaderElement`

Return behavior:

- `findAll()` returns zero or more `ReaderElement` matches.
- `findFirst()` returns the first `ReaderElement` match or `null`.

## What Queries Are For

Use queries when repeated traversal is getting noisy but you still want to stay
inside the compact reader model.

Small example:

```php
$entry = $document->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);
```

## Namespace Behavior

- XML default namespaces are not applied automatically by XPath.
- Map a default namespace to an explicit alias such as `['feed' => 'urn:feed']`.
- Prefixed namespaces already in scope on the query context are registered automatically.

## Boundaries

The query layer is intentionally element-oriented.

- Queries must select elements.
- Attribute-only or text-only XPath results raise `InvalidQueryException`.
- This is not a broad XPath wrapper API.

Common exceptions:

- `InvalidQueryException` for invalid query expressions or unsupported result shapes
- `UnknownQueryNamespacePrefixException` when a query uses an unknown namespace prefix

## Related

- [Overview](overview.md)
- [Reader](reader.md)
- [Import](import.md)
- [Run Reader Queries](../reader/queries.md)
