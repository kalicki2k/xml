# Builder API

The builder-side API is for creating XML in memory through immutable objects.

## `Xml`

`Xml` is the static entry point for creating documents, elements, names, and
common node objects.

Important methods:

- `document(Element $root): XmlDocument`
  Creates a new document with a UTF-8 XML declaration by default.
- `element(string|QualifiedName $name): Element`
  Creates an immutable element node.
- `qname(string $localName, ?string $namespaceUri = null, ?string $prefix = null): QualifiedName`
  Creates an explicit namespace-aware name.
- `text()`, `cdata()`, `comment()`, `processingInstruction()`
  Create standalone node objects when you do not want to rely only on the fluent `Element` helpers.
- `declaration(string $version = '1.0', ?string $encoding = 'UTF-8', ?bool $standalone = null): XmlDeclaration`
  Creates an explicit declaration object.

Small example:

```php
use Kalle\Xml\Builder\Xml;

$document = Xml::document(
    Xml::element('catalog')
        ->child(Xml::element('book')->attribute('isbn', '9780132350884')),
);
```

## `Element`

`Element` is the immutable node you compose into a document tree.

Common accessors:

- `name()`, `qualifiedName()`, `localName()`, `prefix()`, `namespaceUri()`
- `attributes()`, `children()`, `namespaceDeclarations()`

Common modifiers:

- `attribute(string|QualifiedName $name, string|int|float|bool|Stringable|null $value): self`
  Adds or replaces an attribute. Passing `null` removes it.
- `withoutAttribute(string|QualifiedName $name): self`
  Removes an attribute explicitly.
- `child(Node $child): self`
  Appends a child node.
- `text()`, `cdata()`, `comment()`, `processingInstruction()`
  Append common child-node types.
- `declareNamespace(string $prefix, string $uri): self`
- `declareDefaultNamespace(string $uri): self`

Return behavior:

- All modifier methods return a new `Element` instance or the existing one when nothing changes.

Common exceptions:

- `InvalidXmlName`, `InvalidXmlContent`, `InvalidXmlCharacter`
- `DuplicateAttributeException`
- `DuplicateNamespaceDeclarationException`
- `InvalidNamespaceDeclarationException`

## `XmlDocument`

`XmlDocument` wraps a root `Element` and an optional `XmlDeclaration`.

Important methods:

- `root(): Element`
- `declaration(): ?XmlDeclaration`
- `withRoot(Element $root): self`
- `withDeclaration(XmlDeclaration $declaration): self`
- `withoutDeclaration(): self`
- `toString(?WriterConfig $config = null): string`
- `saveToFile(string $path, ?WriterConfig $config = null): void`
- `saveToStream(mixed $stream, ?WriterConfig $config = null): void`

Behavior notes:

- `toString()` and the `saveTo*()` methods serialize through the same writer path used by `StreamingXmlWriter`.
- Serialization and write failures surface writer-side exceptions such as `SerializationException`, `FileWriteException`, or `StreamWriteException`.

## `QualifiedName`

`QualifiedName` is the explicit namespace-aware name object shared by builder
and reader APIs.

Important methods:

- `localName(): string`
- `namespaceUri(): ?string`
- `prefix(): ?string`
- `lexicalName(): string`
- `identityKey(): string`

Use `Xml::qname()` when you want the builder-side convenience method instead of
calling the constructor directly.

## `XmlDeclaration`

`XmlDeclaration` represents the XML declaration attached to a document.

Important methods:

- `version(): string`
- `encoding(): ?string`
- `standalone(): ?bool`
- `withVersion()`, `withEncoding()`, `withStandalone()`

Behavior notes:

- The declaration support is intentionally strict: XML version must stay `1.0`, and encoding must stay `UTF-8` or `null`.

## Related

- [Overview](overview.md)
- [Writer](writer.md)
- [Work with Namespaces](../concepts/namespaces.md)
- [Build Documents with `Xml`](../writer/documents.md)
