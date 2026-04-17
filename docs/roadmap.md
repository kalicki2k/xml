# Roadmap

## v1.2 Status

`kalle/xml` v1.2 now combines two writer paths with a separate read-only reader:

- `Xml` for tree-based XML construction
- `StreamingXmlWriter` for incremental XML writing
- `XmlReader` for small, namespace-aware document and element traversal via `ReaderDocument` and `ReaderElement`

The v1.2 milestone adds:

- loading XML from strings, files, and PHP streams
- read-only document and element traversal without reader concerns leaking into `Xml`
- namespace-aware element names, attribute access, and in-scope namespace inspection
- compact reader traversal centered around `rootElement()`, `firstChildElement()`, and `childElements()`
- parse, file-input, and stream-input exceptions with library-specific messages
- examples and tests for realistic reader workflows alongside the existing writer coverage

## Current Direction

The package remains intentionally writer-focused with a small complementary
reader API. Near-term work should improve writer ergonomics, reader clarity,
correctness, performance visibility, and documentation quality without
expanding into unrelated XML features.

## Out Of Scope

The roadmap still excludes:

- XPath
- XSD validation
- mutation APIs for loaded XML
- XML-to-array or XML-to-object mapping
- broad reader/query APIs beyond the current traversal surface
- general-purpose XML tooling outside writing
