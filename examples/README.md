# Examples

Runnable examples for the public writer, reader, query, import, and validation
APIs.

Use them alongside the docs:

- [Getting Started](../docs/getting-started.md)
- [Writer guides](../docs/writer/README.md)
- [Reader guides](../docs/reader/README.md)
- [Import guides](../docs/import/README.md)
- [Validation guides](../docs/validation/README.md)
- [Choosing an API](../docs/concepts/choosing-an-api.md)
- [Work with Namespaces](../docs/concepts/namespaces.md)
- [API reference](../docs/api/README.md)

## Document Model

- `catalog.php` builds a small pretty-printed catalog with `Xml`.
- `namespaced-feed.php` builds a namespace-aware feed with `Xml::qname()`.

## Streaming Writer

- `streaming-catalog.php` streams a catalog export incrementally to `php://stdout`.
- `streaming-feed.php` streams a namespace-aware feed incrementally to `php://stdout`.
- `streaming-to-file.php` writes to a file path and mixes prebuilt subtrees into the stream.

## Reader

- `reading-catalog.php` loads XML from a string and traverses it read-only.
- `reading-config.php` loads a config-like XML document from a string.
- `reading-feed.php` loads namespace-aware XML from a file and reads prefixed attributes.
- `reading-stream.php` loads an invoice-style XML document from a PHP stream.

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
