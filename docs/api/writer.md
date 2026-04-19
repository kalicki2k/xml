# Writer API

The writer API covers complete-document serialization, incremental output, and
writer configuration.

## `XmlWriter`

`XmlWriter` is the non-streaming serializer for an existing `XmlDocument`.

Important methods:

- `toString(XmlDocument $document, ?WriterConfig $config = null): string`
- `toFile(XmlDocument $document, string $path, ?WriterConfig $config = null): void`
- `toStream(XmlDocument $document, mixed $stream, ?WriterConfig $config = null): void`

Behavior notes:

- `XmlWriter` keeps serialization separate from the immutable document model.
- It applies the same XML output rules as the streaming writer without exposing
  the streaming writer as the public whole-document serializer.

## `StreamingXmlWriter`

`StreamingXmlWriter` emits XML directly to file and stream targets without
requiring a full document tree in memory first.

Constructors:

- `forFile(string $path, ?WriterConfig $config = null): self`
  Writes incrementally to a file path while the writer owns the underlying stream.
- `forStream(mixed $stream, ?WriterConfig $config = null, bool $closeOnFinish = false): self`
  Writes directly to a PHP stream resource.

Manual lifecycle:

- `startDocument(?XmlDeclaration $declaration = null): self`
- `startElement(string|QualifiedName $name): self`
- `writeAttribute(string|QualifiedName $name, string|int|float|bool|Stringable|null $value): self`
- `declareNamespace(string $prefix, string $uri): self`
- `declareDefaultNamespace(string $uri): self`
- `writeText(string $content): self`
- `writeCdata(string $content): self`
- `writeComment(string $content): self`
- `writeProcessingInstruction(string $target, string $data = ''): self`
- `writeElement(Element $element): self`
- `endElement(): self`
- `finish(): void`

Behavior notes:

- The writer is stateful and strict about element open/close order.
- Namespace declarations are resolved automatically from element and attribute names.
- `writeElement()` is the bridge point for mixing in builder-side or imported subtrees.
- Whole-document serialization belongs to `XmlWriter`; use `StreamingXmlWriter`
  when the output itself is incremental.

Common exceptions:

- `SerializationException` for invalid writer state
- `FileWriteException` or `StreamWriteException` for output-target failures

## `WriterConfig`

`WriterConfig` controls output style.

Constructors:

- `compact(bool $emitDeclaration = true, bool $selfCloseEmptyElements = true): self`
- `pretty(string $indent = '    ', string $newline = "\n", bool $emitDeclaration = true, bool $selfCloseEmptyElements = true): self`

Important accessors:

- `prettyPrint()`
- `indent()`
- `newline()`
- `emitDeclaration()`
- `selfCloseEmptyElements()`

Common immutable modifiers:

- `withPrettyPrint(bool $prettyPrint): self`
- `withIndent(string $indent): self`
- `withNewline(string $newline): self`
- `withEmitDeclaration(bool $emitDeclaration): self`
- `withSelfCloseEmptyElements(bool $selfCloseEmptyElements): self`

Common exception:

- `InvalidWriterConfigException` when indent or newline settings are invalid

## Related

- [Overview](overview.md)
- [Builder](builder.md)
- [Stream XML Output](../writer/streaming.md)
