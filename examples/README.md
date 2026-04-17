# Examples

Runnable examples for the public writer, reader, query, and validation APIs:

- `catalog.php` uses the document model for a small pretty-printed catalog
- `query-feed.php` runs a minimal namespace-aware reader query against a feed document and shows how to alias a default namespace for XPath-style queries
- `query-invoice.php` runs document-scoped and element-scoped element queries against a namespace-heavy invoice document
- `reading-catalog.php` loads XML from a string and traverses it read-only
- `reading-config.php` loads a config-like XML document from a string
- `reading-feed.php` loads namespace-aware XML from a file and reads prefixed attributes
- `reading-stream.php` loads an invoice-style XML document from a PHP stream
- `validate-catalog.php` validates a writer-built catalog `XmlDocument` against an inline XSD schema
- `validate-feed.php` validates a namespace-aware writer-built `XmlDocument` against an inline XSD schema
- `namespaced-feed.php` uses the document model with namespace-aware names
- `streaming-catalog.php` streams a catalog export incrementally to `php://stdout`
- `streaming-to-file.php` writes to a file path and mixes prebuilt subtrees into a stream
- `streaming-feed.php` streams a namespace-aware feed incrementally to `php://stdout`

Run them from the repository root after `composer install`:

```bash
php examples/catalog.php
php examples/query-feed.php
php examples/query-invoice.php
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
