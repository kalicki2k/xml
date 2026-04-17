# Roadmap

## v1.4 Status

`kalle/xml` v1.4 now combines two writer paths with a separate read-only reader,
a small query layer built on top of that reader model, and compact XSD
validation as a separate capability:

- `Xml` for tree-based XML construction
- `StreamingXmlWriter` for incremental XML writing
- `XmlReader` for namespace-aware document and element traversal
- `findAll()` and `findFirst()` on `ReaderDocument` and `ReaderElement` for small XPath-style element queries
- `XmlValidator` for validating XML strings, files, streams, and `XmlDocument` instances against XSD schemas

The v1.4 milestone adds:

- loading XML from strings, files, and PHP streams
- read-only document and element traversal without reader concerns leaking into `Xml`
- namespace-aware element names, attribute access, and in-scope namespace inspection
- compact reader traversal centered around `rootElement()`, `firstChildElement()`, and `childElements()`
- a small XPath-style query layer centered around element-oriented `findAll()` and `findFirst()` results on the reader model
- compact XSD validation from schema strings, files, and streams
- validation of XML strings, files, streams, and `XmlDocument` instances against XSD schemas
- namespace-aware validation workflows for writer-built documents and schema-file workflows with relative imports
- validation results with line- and column-aware diagnostics for invalid but well-formed XML
- parse, file-input, and stream-input exceptions with library-specific messages
- examples and tests for realistic reader, query, and validation workflows alongside the existing writer coverage

## Current Direction

The package remains intentionally writer-focused with a small complementary
reader, reader-side query API, and separate validation capability. Near-term
work should improve writer ergonomics, reader clarity, query clarity,
validation clarity, correctness, performance visibility, and documentation
quality without expanding into unrelated XML features or turning the
element-oriented query layer or the XSD support into a broader framework.

## Out Of Scope

The roadmap still excludes:

- full DOM/XPath wrapper APIs beyond the current query layer
- RELAX NG or broader schema-language support
- mutation APIs for loaded XML
- XML-to-array or XML-to-object mapping
- broad reader/query abstractions beyond the current traversal and query surface
- broad schema-framework features beyond the current XSD validation surface
- general-purpose XML tooling outside writing
