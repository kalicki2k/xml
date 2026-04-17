# Examples

Runnable examples for the public writer and reader APIs:

- `catalog.php` uses the document model for a small pretty-printed catalog
- `query-feed.php` runs namespace-aware document-scoped and element-scoped queries against a feed document
- `query-invoice.php` runs document-scoped and element-scoped queries against a namespace-heavy invoice document
- `reading-catalog.php` loads XML from a string and traverses it read-only
- `reading-config.php` loads a config-like XML document from a string
- `reading-feed.php` loads namespace-aware XML from a file and reads prefixed attributes
- `reading-stream.php` loads an invoice-style XML document from a PHP stream
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
php examples/namespaced-feed.php
php examples/streaming-catalog.php
php examples/streaming-to-file.php
php examples/streaming-feed.php
```
