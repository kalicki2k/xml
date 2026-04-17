# Release Process

Keep releases boring: documentation, QA, examples, and benchmarks should agree
before a tag is created.

## Before Tagging

- make sure `README.md`, `examples/`, `benchmarks/`, and `composer.json` describe the same package scope and terminology
- confirm that examples use only the final public API names
- update `docs/roadmap.md` if the release changes milestone status or package direction
- check that README example and benchmark filenames still exist

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
