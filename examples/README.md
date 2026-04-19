# Examples

Runnable examples for tree-based writing, streaming writing, streaming reading,
read-only tree reading, DOM interop, query, import, and validation APIs.

Use them alongside the docs:

- [Getting Started](../docs/getting-started.md)
- [Writer guides](../docs/writer/README.md)
- [Reader guides](../docs/reader/README.md)
- [DOM interop guide](../docs/dom/interop.md)
- [Import guides](../docs/import/README.md)
- [Validation guides](../docs/validation/README.md)
- [Choosing an API](../docs/concepts/choosing-an-api.md)
- [Work with Namespaces](../docs/concepts/namespaces.md)
- [API reference](../docs/api/README.md)

## Document Model

- `catalog.php` builds a small catalog with `XmlBuilder` and serializes the finished document with `XmlWriter`.
- `namespaced-feed.php` builds a namespace-aware feed with `XmlBuilder::qname()` and serializes the finished document with `XmlWriter`.

## Streaming Writer

- `streaming-catalog.php` writes a catalog incrementally to `php://stdout`.
- `streaming-feed.php` writes a namespace-aware feed incrementally to `php://stdout`.
- `streaming-to-file.php` writes incrementally to a file path and mixes prebuilt subtrees into the stream.

## Streaming Reader

- `streaming-reader-catalog.php` reads a catalog file incrementally and expands matching `<book>` elements into the regular reader model.
- `streaming-reader-invoice.php` reads a namespaced invoice stream incrementally, expands one subtree, and imports it back into the writer model.
- `streaming-reader-feed-export.php` streams a namespace-aware feed through non-overlapping `readElements()` records, filters matching `<entry>` records, and writes the imported results back out incrementally.

## Tree Reader

- `reading-catalog.php` loads XML from a string and traverses it read-only.
- `reading-config.php` loads a config-like XML document from a string.
- `reading-feed.php` loads namespace-aware XML from a file and reads prefixed attributes.
- `reading-stream.php` loads an invoice-style XML document from a PHP stream.

## DOM Interop

- `dom-roundtrip.php` exports a writer-built feed into DOM, queries it through `XmlReader`, imports one result back, and writes it again.
- `dom-feed-query.php` exports a realistic namespace-aware feed into DOM and queries it through the reader model.
- `dom-invoice-stream.php` loads an invoice into `DOMDocument`, enters the reader flow from `DOMElement`, imports a subtree, and streams it out.

## Queries

- `query-feed.php` runs a minimal namespace-aware document query and shows how to alias a default namespace for XPath-style expressions.
- `query-invoice.php` runs document-scoped and element-scoped queries against a namespace-heavy invoice document.

## Import

- `import-feed-entry.php` queries a feed entry and imports it into a regular writer-side document.
- `import-invoice-party.php` queries an invoice subtree and streams the imported writer-side element.

## Validation

- `validate-catalog.php` validates a writer-built `XmlDocument` against an inline XSD schema.
- `validate-feed.php` validates a namespace-aware writer-built `XmlDocument` against an inline XSD schema.

## Run the Examples

Run them from the repository root after `composer install`:

```bash
php examples/catalog.php
php examples/query-feed.php
php examples/query-invoice.php
php examples/import-feed-entry.php
php examples/import-invoice-party.php
php examples/streaming-reader-catalog.php
php examples/streaming-reader-invoice.php
php examples/streaming-reader-feed-export.php
php examples/dom-roundtrip.php
php examples/dom-feed-query.php
php examples/dom-invoice-stream.php
php examples/reading-catalog.php
php examples/reading-config.php
php examples/reading-feed.php
php examples/reading-stream.php
php examples/validate-catalog.php
php examples/validate-feed.php
php examples/namespaced-feed.php
php examples/streaming-catalog.php
php examples/streaming-to-file.php
php examples/streaming-feed.php
```
