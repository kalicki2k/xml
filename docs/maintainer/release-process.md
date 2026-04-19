# Release Process

Keep releases boring: public docs, examples, QA, and package metadata should
agree before a tag is created.

## Before Tagging

- make sure `CHANGELOG.md`, `README.md`, `docs/`, `examples/`, and `composer.json` describe the same package scope and terminology
- confirm that public guides and examples use only the final public API names
- confirm that writer-side docs and examples keep the final split explicit:
  `XmlBuilder` builds, `XmlWriter` serializes complete documents, and `StreamingXmlWriter` streams incrementally
- update `roadmap.md` if the release changes the current package scope or package direction
- check that README and docs links still point to existing files
- confirm that `docs/api/` still matches the public class and method surface
- confirm that DOM interop docs and examples still match `XmlDomBridge` and the DOM entry points on `XmlReader`
- confirm that reader-query examples still match the documented `findFirst()` / `findAll()` API
- confirm that import examples still match the documented `XmlImporter` API
- confirm that validation examples still match the documented `XmlValidator` API
- build any release archive from a clean worktree only; do not package `vendor/`, caches, benchmark output, or editor files

## Required QA

Run the standard quality gates from the repository root:

```bash
composer validate --strict --no-check-lock
composer test
composer stan
composer cs-check
```

## Recommended Smoke Checks

Use a small set of example runs that covers the public release surface:

```bash
php examples/catalog.php > /tmp/kalle-example-catalog.xml
php examples/namespaced-feed.php
php examples/streaming-catalog.php > /tmp/kalle-example-streaming-catalog.xml
php examples/streaming-feed.php > /tmp/kalle-example-streaming-feed.xml
php examples/streaming-to-file.php
php examples/streaming-reader-catalog.php
php examples/streaming-reader-invoice.php
php examples/streaming-reader-feed-export.php
php examples/reading-catalog.php
php examples/query-feed.php
php examples/query-invoice.php
php examples/import-feed-entry.php
php examples/import-invoice-party.php
php examples/dom-roundtrip.php
php examples/dom-feed-query.php
php examples/dom-invoice-stream.php
php examples/validate-catalog.php
php examples/validate-feed.php
```

## Final Release Check

- review `git status` and the final diff
- check commit messages for accidental work-in-progress noise
- keep release archives, benchmark output, and local caches out of the repository
- create the release commit and tag only after docs and QA are in sync
