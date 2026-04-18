# Release Process

Keep releases boring: public docs, examples, QA, and package metadata should
agree before a tag is created.

## Before Tagging

- make sure `README.md`, `docs/`, `examples/`, and `composer.json` describe the same package scope and terminology
- confirm that public guides and examples use only the final public API names
- update `roadmap.md` if the release changes milestone status or package direction
- check that README and docs links still point to existing files
- confirm that `docs/api/` still matches the public class and method surface
- confirm that reader-query examples still match the documented `findFirst()` / `findAll()` API
- confirm that import examples still match the documented `XmlImporter` API
- confirm that validation examples still match the documented `XmlValidator` API

## Required QA

Run the standard quality gates from the repository root:

```bash
composer validate --strict
composer test
composer stan
composer cs-check
```

## Recommended Smoke Checks

Use the smoke checks that match the release surface:

```bash
php examples/catalog.php > /tmp/kalle-example-catalog.xml
php examples/query-feed.php
php examples/query-invoice.php
php examples/import-feed-entry.php
php examples/import-invoice-party.php
php examples/validate-catalog.php
php examples/validate-feed.php
php examples/reading-catalog.php
php examples/reading-config.php
php examples/reading-feed.php
php examples/reading-stream.php
php examples/streaming-catalog.php > /tmp/kalle-example-streaming-catalog.xml
php examples/streaming-feed.php > /tmp/kalle-example-streaming-feed.xml
php examples/streaming-to-file.php
php benchmarks/write-performance.php small 1
php benchmarks/document-vs-streaming.php 10 1
```

## Final Release Check

- review `git status` and the final diff
- check commit messages for accidental work-in-progress noise
- keep benchmark output out of the repository
- create the release commit and tag only after docs and QA are in sync
