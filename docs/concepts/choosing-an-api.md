# Choosing an API

`kalle/xml` stays compact, but it still exposes several entry points. Choose
the smallest one that fits the job.

## Writing XML

- Use `XmlBuilder` when you want to build an XML tree in memory, reuse subtrees, or keep tests and fixtures readable.
- Use `XmlWriter` when you already have a built document and want to serialize it to a string, file, or stream.
- Use `StreamingXmlWriter` when output is incremental, large, or should go directly to a file path or PHP stream.

Those three are intentionally separate: build first, serialize complete
documents with `XmlWriter`, or bypass the tree and stream imperatively with
`StreamingXmlWriter`.

## Reading XML

- Use `StreamingXmlReader` when input is large or incremental and cursor-style processing, non-overlapping record-by-record iteration through `readElements()`, subtree extraction, or filtered export is enough.
- Use `XmlReader` when you need a loaded tree for read-only traversal, parent/child navigation, or queries.
- Use `findAll()` and `findFirst()` when filtered element lookups are clearer than repeated traversal.

## Bridging and Validation

- Use `XmlDomBridge` plus `XmlReader::fromDomDocument()` or `XmlReader::fromDomElement()` when existing code already works with native DOM values.
- Use `XmlImporter` when loaded or queried XML needs to move back into the writer-side model.
- Use `XmlValidator` when well-formed XML is not enough and the document must match an XSD schema.

## Keep the APIs Separate

The package intentionally keeps writing, reading, querying, importing, and
validation as separate capabilities. That keeps each surface small and avoids
blurring the library into a broad XML framework.

## Related

- [Getting Started](../getting-started.md)
- [Writer guides](../writer/README.md)
- [Reader guides](../reader/README.md)
- [DOM interop guide](../dom/interop.md)
- [Import guides](../import/README.md)
- [Validation guides](../validation/README.md)
