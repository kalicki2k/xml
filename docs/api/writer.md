# Writer API

The writer API covers incremental output and writer configuration.

## `StreamingXmlWriter`

`StreamingXmlWriter` emits XML directly to a target without requiring a full
document tree in memory first.

Constructors:

- `forString(?WriterConfig $config = null): self`
  Buffers output in memory. Use `toString()` after `finish()`.
- `forFile(string $path, ?WriterConfig $config = null): self`
  Writes directly to a file path.
- `forStream(mixed $stream, ?WriterConfig $config = null, bool $closeOnFinish = false): self`
  Writes directly to a PHP stream resource.

One-shot output:

- `writeDocument(XmlDocument $document): self`
  Writes a complete builder-side document through the streaming path.

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

Buffered output:

- `toString(): string`
  Available only for `forString()` writers after `finish()`.

Behavior notes:

- The writer is stateful and strict about element open/close order.
- Namespace declarations are resolved automatically from element and attribute names.
- `writeElement()` is the bridge point for mixing in builder-side or imported subtrees.

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
