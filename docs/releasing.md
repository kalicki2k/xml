# Releasing

This project is intentionally small, so the release workflow should stay
simple and repeatable.

## Before Tagging

- make sure `README.md`, `examples/`, `benchmarks/`, and `composer.json` still describe the same package positioning
- confirm that new examples use the final public API names only
- review `docs/roadmap.md` and update milestone status if the release changes it
- check that benchmark and example filenames still match what the README references

## Required QA

Run the standard quality gates from the repository root:

```bash
composer test
composer stan
composer cs-check
```

Recommended smoke checks for writer- and reader-facing releases:

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

## Release Readiness Notes

- keep the package focused; position the reader as a small companion API, not as the start of a parser framework
- prefer updating wording and examples over adding compatibility shims for pre-public API names
- if README messaging changes, mirror the same terminology in `composer.json`, `examples/README.md`, and `benchmarks/README.md`
- if reader messaging changes, mirror the same terminology in `README.md`, `examples/README.md`, and `docs/roadmap.md`
- benchmark results are for maintainer signal, not release marketing

## Tagging

- review `git status`
- inspect the final diff and commit history
- create the release commit and tag only after QA and documentation are in sync
