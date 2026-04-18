# API Overview

`kalle/xml` exposes a compact set of public entry points. The API is split by
job rather than trying to hide everything behind one broad XML object model or
grow into a broad XML framework.

## Builder Side

Use the builder-side types when you are creating XML in memory.

- `Xml` is the static entry point for building documents, elements, names, and common node types.
- `Element` is the immutable element node used to compose a document tree.
- `XmlDocument` is the immutable document wrapper around a root `Element` and an optional declaration.
- `QualifiedName` represents an explicit namespace-aware name.
- `XmlDeclaration` represents the XML declaration attached to a document.

Continue with [Builder](builder.md).

## Streaming Output

Use `StreamingXmlWriter` when XML should be emitted incrementally or written
directly to a string buffer, file path, or stream resource.

- `StreamingXmlWriter` handles document writing, manual streaming, and mixed workflows that write prebuilt `Element` subtrees.
- `WriterConfig` controls compact vs pretty output, declaration emission, indentation, and empty-element style.

Continue with [Writer](writer.md).

## Reading and Querying

Use the reader-side types when you are loading existing XML read-only.

- `XmlReader` loads XML from strings, files, and streams.
- `ReaderDocument` exposes the document root and document-scoped queries.
- `ReaderElement` exposes traversal, attribute access, text access, and element-scoped queries.
- `findAll()` and `findFirst()` form the small query layer on top of the reader model.

Continue with [Reader](reader.md) and [Query](query.md).

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
- `ValidationException` covers schema-setup failures.
- `XmlException` is the common root for library-specific runtime errors.

Continue with [Exceptions](exceptions.md).
