# Reader API

The reader API loads existing XML and exposes it through a compact read-only
model.

Use this page for the tree/DOM-backed reader model. For large or incremental
input, continue with [Streaming reader](streaming-reader.md).

## `XmlReader`

`XmlReader` is the loading entry point.

Important methods:

- `fromString(string $xml): ReaderDocument`
- `fromFile(string $path): ReaderDocument`
- `fromStream(mixed $stream): ReaderDocument`
- `fromDomDocument(DOMDocument $document): ReaderDocument`
- `fromDomElement(DOMElement $element): ReaderElement`

Behavior notes:

- `fromString()`, `fromFile()`, `fromStream()`, and `fromDomDocument()` return `ReaderDocument`.
- Malformed XML raises `ParseException`.
- Unreadable files or streams raise `FileReadException` or `StreamReadException`.
- `fromDomDocument()` wraps an existing DOM document directly and raises `DomInteropException` when no document element is present.
- `fromDomElement()` wraps one DOM subtree directly.
- DOM-backed loading still returns the normal reader model; it does not introduce a DOM-specific reader API.

## `ReaderDocument`

`ReaderDocument` is the document-level reader wrapper.

Important methods:

- `rootElement(): ReaderElement`
- `findAll(string $expression, array $namespaces = []): list<ReaderElement>`
- `findFirst(string $expression, array $namespaces = []): ?ReaderElement`

Behavior notes:

- `rootElement()` is the normal starting point for traversal.
- The query methods are documented in more detail in [Query](query.md).

## `ReaderElement`

`ReaderElement` exposes traversal, text access, attributes, and element-scoped
queries.

Name access:

- `name()`
- `qualifiedName()`
- `localName()`
- `prefix()`
- `namespaceUri()`

Tree access:

- `text(): string`
- `parent(): ?ReaderElement`
- `childElements(string|QualifiedName|null $name = null): array`
- `firstChildElement(string|QualifiedName|null $name = null): ?ReaderElement`

Attribute and namespace access:

- `attributes(): array`
- `hasAttribute(string|QualifiedName $name): bool`
- `attribute(string|QualifiedName $name): ?Attribute`
- `attributeValue(string|QualifiedName $name): ?string`
- `namespacesInScope(): array`

Element-scoped queries:

- `findAll(string $expression, array $namespaces = []): list<ReaderElement>`
- `findFirst(string $expression, array $namespaces = []): ?ReaderElement`

Behavior notes:

- This is a read-only model. It does not mutate the loaded XML.
- Use `QualifiedName` when a lookup must match a specific namespace URI.
- If a loaded or queried subtree needs writer-side operations, continue with `XmlImporter`.

## Related

- [Overview](overview.md)
- [Streaming reader](streaming-reader.md)
- [DOM interop](dom.md)
- [Query](query.md)
- [Import](import.md)
- [Traverse XML with `XmlReader`](../reader/traversal.md)
