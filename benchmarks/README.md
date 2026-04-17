# Benchmarks

The scripts in this directory are meant as practical maintenance tools, not as
marketing material.

## What Is Measured

The main suite, `write-performance.php`, measures end-to-end XML writing for:

- `kalle/xml` through the `Xml` document model
- `StreamingXmlWriter`
- `DOMDocument`, when `ext-dom` is available
- `XMLWriter`, when `ext-xmlwriter` is available

Covered scenarios:

- small document writing
- medium document writing
- large document writing
- namespace-heavy document writing

Each implementation reports:

- runtime: total time and average time per iteration
- memory: maximum peak-memory delta per iteration relative to the starting baseline

`document-vs-streaming.php` remains as a smaller, focused benchmark for the two
internal `kalle/xml` write paths.

## Running the Benchmarks

From the repository root:

```bash
php benchmarks/write-performance.php
php benchmarks/write-performance.php medium
php benchmarks/write-performance.php namespace-heavy 25
php benchmarks/write-performance.php 50
```

Arguments for `write-performance.php`:

- first argument optional: scenario (`small`, `medium`, `large`, `namespace-heavy`, `all`)
- second argument optional: iteration override for the selected scenarios
- if only a number is passed, it is treated as the iteration override for all scenarios

Focused `kalle/xml` comparison:

```bash
php benchmarks/document-vs-streaming.php
php benchmarks/document-vs-streaming.php 5000 15
```

## How To Interpret Results

The suite deliberately measures the practical write path of each API rather
than an isolated micro-operation. In practice that means:

- the `Xml` document model includes document construction plus output
- `StreamingXmlWriter` and `XMLWriter` write incrementally
- `DOMDocument` includes DOM construction plus `saveXML()`

The results therefore show usage profiles and broad trends, not an absolute
"winner".

Before timing starts, each implementation is checked once against the semantic
baseline. That keeps the suite from benchmarking accidentally different XML.

## Limitations

- CLI microbenchmarks are sensitive to CPU load, turbo boost, and memory state
- there is no process isolation or statistical analysis across separate runs
- peak-memory reporting is a practical approximation, not a full heap analysis
- `DOMDocument` and `XMLWriter` results depend on installed PHP extensions and the active PHP version
- real applications may spend more time in I/O, data loading, or object construction outside the writer itself

Do not commit generated reports or one-off profiling artifacts.
