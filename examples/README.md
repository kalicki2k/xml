# Examples

Runnable examples for the public writer APIs:

- `catalog.php` uses the document model for a small pretty-printed catalog
- `namespaced-feed.php` uses the document model with namespace-aware names
- `streaming-catalog.php` streams a catalog export incrementally to `php://stdout`
- `streaming-feed.php` streams a namespace-aware feed incrementally to `php://stdout`

Run them from the repository root after `composer install`:

```bash
php examples/catalog.php
php examples/namespaced-feed.php
php examples/streaming-catalog.php
php examples/streaming-feed.php
```
