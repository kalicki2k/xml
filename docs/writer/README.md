# Writer Guides

Use the writer guides when producing XML.

`kalle/xml` has two writer paths:

- `XmlBuilder` plus `XmlWriter` for immutable, tree-based document construction and complete-document serialization
- `StreamingXmlWriter` for incremental output to files and streams

Use the first path when you want an in-memory model. Use the second when you
want imperative output and do not need a full document tree first.

## Guides

- [Build Documents with `XmlBuilder`](documents.md)
- [Stream XML Output](streaming.md)

## Related

- [Getting Started](../getting-started.md)
- [Choosing an API](../concepts/choosing-an-api.md)
- [DOM interop guide](../dom/interop.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [API reference](../api/README.md)
