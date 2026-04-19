# Roadmap

## Current Package Scope

`kalle/xml` currently combines two writer paths with a separate streaming
reader, a separate read-only tree reader, a small query layer built on top of
that tree reader model, explicit DOM interop, a compact reader-to-writer
import bridge, and compact XSD validation as separate capabilities:

- `XmlBuilder` for tree-based XML construction
- `XmlWriter` for whole-document serialization
- `StreamingXmlWriter` for incremental XML writing
- `StreamingXmlReader` for incremental, cursor-based XML reading from files and streams
- `readElements()` and `StreamedElement` for compact record-by-record stream processing on top of the streaming reader
- `XmlReader` for namespace-aware document and element traversal
- `XmlDomBridge` plus DOM entry points on `XmlReader` for explicit DOM interop
- `findAll()` and `findFirst()` on `ReaderDocument` and `ReaderElement` for small XPath-style element queries
- `XmlImporter` for importing `ReaderDocument` and `ReaderElement` into the immutable writer-side model
- `XmlValidator` for validating XML strings, files, streams, and `XmlDocument` instances against XSD schemas

The current package scope includes:

- loading XML from strings, files, and PHP streams
- incremental, namespace-aware streaming XML reading from files and streams
- a compact streaming cursor API centered on `read()`, `nodeType()`, `isStartElement()`, `expandElement()`, and `extractElementXml()`
- subtree extraction from `StreamingXmlReader` back into the existing `ReaderElement`, `XmlReader`, `XmlImporter`, `XmlValidator`, and writer flows
- compact record-by-record iteration centered on `StreamingXmlReader::readElements()` and `StreamedElement`
- read-only document and element traversal without reader concerns leaking into `XmlBuilder`
- namespace-aware element names, attribute access, and in-scope namespace inspection
- compact reader traversal centered around `rootElement()`, `firstChildElement()`, and `childElements()`
- a small XPath-style query layer centered around element-oriented `findAll()` and `findFirst()` results on the reader model
- explicit DOM export centered on `XmlDomBridge::toDomDocument()` and `XmlDomBridge::elementToDomDocument()`
- entering the reader flow directly from `DOMDocument` and `DOMElement` through `XmlReader`
- namespace- and content-fidelity coverage for writer -> DOM -> reader/query/import/write workflows
- realistic DOM interop examples and integration tests alongside the existing reader, import, and validation coverage
- compact XSD validation from schema strings, files, and streams
- validation of XML strings, files, streams, and `XmlDocument` instances against XSD schemas
- compact import of `ReaderDocument` into `XmlDocument` and `ReaderElement` into `Element`
- imported results stay regular immutable writer-side `XmlDocument` and `Element` instances
- namespace-aware reader-to-query-to-import-to-write workflows
- realistic streaming-reader examples and integration tests, including filtered export workflows, alongside the existing reader, import, and validation coverage
- import coverage for mixed content, namespaced documents, and unsupported DTD/entity cases
- namespace-aware validation workflows for writer-built documents and schema-file workflows with relative imports
- validation results with line- and column-aware diagnostics for invalid but well-formed XML
- parse, file-input, and stream-input exceptions with library-specific messages
- examples and tests for realistic reader, query, import, and validation workflows alongside the existing writer coverage

## Current Direction

The package remains intentionally writer-focused with a small complementary
streaming reader, a small complementary tree reader, a reader-side query API,
explicit DOM interop, a compact import bridge, and separate validation
capability. Near-term work should improve writer ergonomics, reader clarity,
DOM interop clarity, query clarity, import
clarity, validation clarity, correctness, performance visibility, and
documentation quality without expanding into unrelated XML features or turning
the element-oriented query layer, the streaming reader, the DOM interop, the
import bridge, or the
XSD support into broader frameworks.

## Out Of Scope

The current roadmap excludes:

- full DOM/XPath wrapper APIs beyond the current query layer
- broad SAX/event-bus abstractions beyond the current streaming cursor
- RELAX NG or broader schema-language support
- mutation APIs for loaded XML
- XML-to-array or XML-to-object mapping
- broad transformation DSLs or diff/patch/merge engines
- broad reader/query abstractions beyond the current traversal and query surface
- broad schema-framework features beyond the current XSD validation surface
- general-purpose XML tooling outside writing
