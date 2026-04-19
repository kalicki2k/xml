# Streaming Reader API

The streaming reader API handles large or incremental XML input through a small
cursor instead of the regular document tree model.

## `StreamingXmlReader`

`StreamingXmlReader` is the streaming input entry point.

Important methods:

- `fromFile(string $path): StreamingXmlReader`
- `fromStream(mixed $stream): StreamingXmlReader`
- `read(): bool`
- `readElements(string|QualifiedName $name): iterable<StreamedElement>`
- `isOpen(): bool`
- `hasCurrentNode(): bool`
- `close(): void`

Current-node access:

- `nodeType(): ?StreamingNodeType`
- `depth(): ?int`
- `name(): ?string`
- `localName(): ?string`
- `prefix(): ?string`
- `namespaceUri(): ?string`
- `value(): ?string`

Element-oriented helpers:

- `isStartElement(string|QualifiedName|null $name = null): bool`
- `isEndElement(string|QualifiedName|null $name = null): bool`
- `isText(): bool`
- `isComment(): bool`
- `isCdata(): bool`
- `isEmptyElement(): bool`
- `attributes(): array`
- `hasAttribute(string|QualifiedName $name): bool`
- `attribute(string|QualifiedName $name): ?Attribute`
- `attributeValue(string|QualifiedName $name): ?string`
- `extractElementXml(): string`
- `expandElement(): ReaderElement`

Behavior notes:

- `read()` advances one native XML node at a time and returns `false` at normal end of input.
- `readElements()` yields non-overlapping matching start elements as `StreamedElement` snapshots.
- once `readElements('entry')` yields one `<entry>`, nested `<entry>` descendants stay inside that yielded record and are not yielded separately.
- treat `readElements()` as one record loop; if the workflow needs node-level cursor control, use `read()` instead.
- `isOpen()` reports whether the cursor can still read.
- `hasCurrentNode()` reports whether `read()` has positioned the cursor on a current node.
- Current-node accessors such as `nodeType()`, `name()`, and `namespaceUri()` return `null` before the first successful `read()`, after normal end of input, or after `close()`.
- The streaming cursor stays separate from `ReaderDocument` / `ReaderElement`; only `expandElement()` materializes one subtree back into the regular reader model.
- `extractElementXml()` returns the current start element subtree as XML without an XML declaration.
- `fromFile()` and `fromStream()` are namespace-aware because the underlying parser is namespace-aware.
- default namespaces apply to streamed elements, not automatically to plain attributes.
- use `QualifiedName` for namespace-aware attribute lookups instead of prefixed `name` strings.
- Element helpers return `false`, `null`, or `[]` when the cursor is not on a current start element.
- subtree extraction does not advance the cursor; the reader stays on the same start element until the next `read()`.
- `close()` is idempotent.
- use `read()` directly when you need every node or intentionally want nested matching elements instead of record-style subtree iteration.
- `readElements()` is exact-name record iteration, not a general streaming query layer.

## `StreamedElement`

`StreamedElement` is the compact record snapshot yielded by `readElements()`.

Important methods:

- `name(): string`
- `qualifiedName(): QualifiedName`
- `localName(): string`
- `prefix(): ?string`
- `namespaceUri(): ?string`
- `attributes(): array`
- `hasAttribute(string|QualifiedName $name): bool`
- `attribute(string|QualifiedName $name): ?Attribute`
- `attributeValue(string|QualifiedName $name): ?string`
- `toReaderElement(): ReaderElement`
- `toXmlString(): string`
- `toWriterElement(): Element`
- `validate(XmlValidator $validator): ValidationResult`

Behavior notes:

- `toReaderElement()` returns the regular read-only subtree model for traversal and `findFirst()` / `findAll()`.
- `toXmlString()` returns the selected subtree as XML without a declaration.
- `toWriterElement()` reuses `XmlImporter` to move the record into the immutable writer-side model.
- `validate()` is only a thin shorthand for `$validator->validateString($record->toXmlString())`.

Common exceptions:

- `FileReadException` or `StreamReadException` when input cannot be opened
- `ParseException` when the streamed XML is malformed
- `StreamingReaderException` when an operation requires an open reader or a current start element

## `StreamingNodeType`

`StreamingNodeType` is the node-type enum returned by `nodeType()`.

Common cases in application code:

- `Element`
- `EndElement`
- `Text`
- `Cdata`
- `Comment`
- `ProcessingInstruction`

Example:

```php
<?php

declare(strict_types=1);

while ($reader->read()) {
    if (!$reader->isStartElement('entry')) {
        continue;
    }

    $entry = $reader->expandElement();

    echo $entry->attributeValue('sku') . "\n";
}
```

Record-oriented example:

```php
<?php

declare(strict_types=1);

foreach ($reader->readElements('entry') as $entryRecord) {
    if ($entryRecord->attributeValue('sku') === 'item-1002') {
        continue;
    }

    echo $entryRecord->toReaderElement()->findFirst('./title')?->text() . "\n";
}
```

## Related

- [Overview](overview.md)
- [Reader](reader.md)
- [Import](import.md)
- [Read Large XML with `StreamingXmlReader`](../reader/streaming.md)
