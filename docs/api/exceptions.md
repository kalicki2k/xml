# Exceptions

`kalle/xml` uses dedicated exception types under `Kalle\Xml\Exception\` so
loading, writing, querying, importing, and validation failures stay explicit.

## Common Root

- `XmlException`
  Common base class for library-specific runtime failures.

If you want one broad catch point for package errors, catch `XmlException`.

## Writer and Serialization

- `SerializationException`
  Invalid writer state, invalid write order, or unsupported serialization flow.
- `FileWriteException`
  File-path write failure.
- `StreamWriteException`
  Stream-target write failure.
- `InvalidWriterConfigException`
  Invalid `WriterConfig` settings.

Builder-side validation also uses dedicated exceptions such as:

- `InvalidXmlName`
- `InvalidXmlContent`
- `InvalidXmlCharacter`
- `InvalidXmlDeclarationException`
- `InvalidNamespaceDeclarationException`
- `DuplicateAttributeException`
- `DuplicateNamespaceDeclarationException`

## Reading and Querying

- `ReadException`
  Common base class for read-side failures.
- `FileReadException`
  File input could not be read.
- `StreamReadException`
  Stream input could not be read.
- `StreamingReaderException`
  `StreamingXmlReader` is closed, not positioned on a start element, or cannot materialize the current subtree for `expandElement()` or `extractElementXml()`.
- `ParseException`
  Input was readable but malformed XML.
- `QueryException`
  Common base class for query failures.
- `InvalidQueryException`
  Query expression or result shape is invalid for the public query API.
- `UnknownQueryNamespacePrefixException`
  Query references a namespace prefix that was not registered.

## DOM Interop

- `DomInteropException`
  DOM export failed or a DOM value could not be entered into the reader flow.

## Import

- `ImportException`
  Reader-side content could not be imported into the writer model.

## Validation

- `ValidationException`
  Common base class for validation-setup failures.
- `InvalidSchemaException`
  The XSD schema itself is invalid or could not be compiled.

Behavior note:

- Invalid-but-well-formed XML does not use `ValidationException`; it returns an invalid `ValidationResult` instead.

## Catching Strategy

Practical catch points:

- Catch `XmlException` for one broad library-level catch.
- Catch `ReadException` when loading from files, streams, or strings.
- Catch `StreamingReaderException` when the risky boundary is streaming-reader cursor state or subtree expansion.
- Catch `DomInteropException` when the risky boundary is native DOM export or entering the reader flow from `DOMDocument` or `DOMElement`.
- Catch `QueryException` when user-supplied query expressions may fail.
- Catch `ValidationException` when schema setup is the risky part.

## Related

- [Overview](overview.md)
- [DOM interop](dom.md)
- [Reader](reader.md)
- [Query](query.md)
- [Validation](validation.md)
