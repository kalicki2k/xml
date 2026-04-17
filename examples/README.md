# Examples

Runnable examples for the public writer and reader APIs:

- `catalog.php` uses the document model for a small pretty-printed catalog
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
php examples/reading-catalog.php
php examples/reading-config.php
php examples/reading-feed.php
php examples/reading-stream.php
php examples/namespaced-feed.php
php examples/streaming-catalog.php
php examples/streaming-to-file.php
php examples/streaming-feed.php
```
