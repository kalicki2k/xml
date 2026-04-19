# API Overview

`kalle/xml` exposes a compact set of public entry points. The API is split by
job rather than trying to hide everything behind one broad XML object model or
grow into a broad XML framework.

## Builder Side

Use the builder-side types when you are creating XML in memory.

- `XmlBuilder` is the static entry point for building documents, elements, names, and common node types.
- `Element` is the immutable element node used to compose a document tree.
- `XmlDocument` is the immutable document wrapper around a root `Element` and an optional declaration.
- `QualifiedName` represents an explicit namespace-aware name.
- `XmlDeclaration` represents the XML declaration attached to a document.

Continue with [Builder](builder.md).

## Writer Output

Use `XmlWriter` when a complete `XmlDocument` is already built and should be
serialized in one step.

- `XmlWriter` serializes `XmlDocument` instances to strings, files, and streams.
- `StreamingXmlWriter` handles manual incremental output and mixed workflows that write prebuilt `Element` subtrees to file and stream targets.
- `WriterConfig` controls compact vs pretty output, declaration emission, indentation, and empty-element style.

The split is deliberate: build with `XmlBuilder`, serialize complete documents
with `XmlWriter`, or stream incrementally with `StreamingXmlWriter`.

Continue with [Writer](writer.md).

## Streaming Input

Use `StreamingXmlReader` when XML input is large, incremental, or should be
processed one subtree at a time.

- `StreamingXmlReader` reads from files and stream resources through a cursor-based API.
- `readElements()` yields non-overlapping exact-name `StreamedElement` records for large-XML workflows.
- `StreamingNodeType` describes the current native XML node type.
- `expandElement()` materializes one matching start element back into a regular `ReaderElement`.
- `extractElementXml()` returns one matching start element subtree as XML without loading the whole document tree.
- `StreamedElement` keeps common attribute access small and offers explicit conversions back into reader, validation, and writer flows.

Continue with [Streaming reader](streaming-reader.md).

## Reading and Querying

Use the reader-side types when you are loading existing XML read-only.

- `XmlReader` loads XML from strings, files, and streams.
- `ReaderDocument` exposes the document root and document-scoped queries.
- `ReaderElement` exposes traversal, attribute access, text access, and element-scoped queries.
- `findAll()` and `findFirst()` form the small query layer on top of the reader model.

Continue with [Reader](reader.md) and [Query](query.md).

## DOM Interop

Use DOM interop when existing code already works with native DOM values.

- `XmlDomBridge::toDomDocument()` exports `XmlDocument` into `DOMDocument`.
- `XmlDomBridge::elementToDomDocument()` exports one writer-side subtree into a one-root `DOMDocument`.
- `XmlReader::fromDomDocument()` and `XmlReader::fromDomElement()` enter the existing reader flow directly from DOM.

Continue with [DOM interop](dom.md).

## Import and Validation

Use these capabilities when reader and writer workflows need to meet, or when
schema validation matters.

- `XmlImporter` converts `ReaderDocument` or `ReaderElement` results into regular writer-side objects.
- `XmlValidator` binds an XSD schema and validates XML strings, files, streams, or `XmlDocument` instances.
- `ValidationResult` and `ValidationError` report invalid-but-well-formed XML without turning every failure into an exception.

Continue with [Import](import.md) and [Validation](validation.md).

## Exceptions

Most library failures use dedicated exception types under
`Kalle\Xml\Exception\`.

- `SerializationException` covers writer-state problems.
- `ReadException` and `QueryException` cover loading and query failures.
- `StreamingReaderException` covers streaming-reader state and element-expansion misuse.
- `ValidationException` covers schema-setup failures.
- `DomInteropException` covers DOM-export and DOM-entry-point failures.
- `XmlException` is the common root for library-specific runtime errors.

Continue with [Exceptions](exceptions.md).
