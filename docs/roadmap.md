# Roadmap

## v1.3 Status

`kalle/xml` v1.3 now combines two writer paths with a separate read-only reader
and a small query layer built on top of that reader model:

- `Xml` for tree-based XML construction
- `StreamingXmlWriter` for incremental XML writing
- `XmlReader` for namespace-aware document and element traversal
- `findAll()` and `findFirst()` on `ReaderDocument` and `ReaderElement` for small XPath-style element queries

The v1.3 milestone adds:

- loading XML from strings, files, and PHP streams
- read-only document and element traversal without reader concerns leaking into `Xml`
- namespace-aware element names, attribute access, and in-scope namespace inspection
- compact reader traversal centered around `rootElement()`, `firstChildElement()`, and `childElements()`
- a small XPath-style query layer centered around `findAll()` and `findFirst()` on the reader model
- parse, file-input, and stream-input exceptions with library-specific messages
- examples and tests for realistic reader and query workflows alongside the existing writer coverage

## Current Direction

The package remains intentionally writer-focused with a small complementary
reader and reader-side query API. Near-term work should improve writer
ergonomics, reader clarity, query clarity, correctness, performance
visibility, and documentation quality without expanding into unrelated XML
features or turning the query layer into a broader framework.

## Out Of Scope

The roadmap still excludes:

- full DOM/XPath wrapper APIs beyond the current query layer
- XSD validation
- mutation APIs for loaded XML
- XML-to-array or XML-to-object mapping
- broad reader/query abstractions beyond the current traversal and query surface
- general-purpose XML tooling outside writing
