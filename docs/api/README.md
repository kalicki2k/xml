# API Reference

Human-friendly API reference for the public `kalle/xml` surface.

This section focuses on the classes and methods package users will usually
touch. It stays concise on purpose: use the capability guides for workflows and
examples, then use this reference when you need to confirm the role of a class,
the main methods it exposes, or the kind of result or exception to expect.

## Pages

- [Overview](overview.md): map of the public API and where each part fits
- [Builder](builder.md): `XmlBuilder`, `Element`, `XmlDocument`, `QualifiedName`, and `XmlDeclaration`
- [Writer](writer.md): `XmlWriter`, `StreamingXmlWriter`, and `WriterConfig`
- [Reader](reader.md): `XmlReader`, `ReaderDocument`, and `ReaderElement`
- [Streaming reader](streaming-reader.md): `StreamingXmlReader` and `StreamingNodeType`
- [DOM interop](dom.md): `XmlDomBridge` plus the DOM entry points on `XmlReader`
- [Query](query.md): `findAll()` and `findFirst()` on the reader model
- [Import](import.md): `XmlImporter`
- [Validation](validation.md): `XmlValidator`, `ValidationResult`, and `ValidationError`
- [Exceptions](exceptions.md): the main exception groups and where they come from

## Related

- [Getting Started](../getting-started.md)
- [Writer guides](../writer/README.md)
- [Reader guides](../reader/README.md)
- [Import guides](../import/README.md)
- [Validation guides](../validation/README.md)
- [Examples](../../examples/README.md)
