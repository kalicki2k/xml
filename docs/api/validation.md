# Validation API

The validation API binds an XSD schema and validates XML inputs against it.

## `XmlValidator`

`XmlValidator` represents one compiled schema source.

Constructors:

- `fromString(string $schema): self`
- `fromFile(string $path): self`
- `fromStream(mixed $stream): self`

Validation methods:

- `validateString(string $xml): ValidationResult`
- `validateFile(string $path): ValidationResult`
- `validateStream(mixed $stream): ValidationResult`
- `validateXmlDocument(XmlDocument $document): ValidationResult`

Behavior notes:

- `fromFile()` keeps schema-file validation path-based, so relative `xs:include` and `xs:import` locations continue to work.
- Invalid but well-formed XML returns a `ValidationResult` rather than throwing immediately.
- Malformed XML still raises `ParseException`.
- Invalid schemas raise `InvalidSchemaException`.

## `ValidationResult`

`ValidationResult` represents the outcome of one validation run.

Important methods:

- `isValid(): bool`
- `errors(): list<ValidationError>`
- `firstError(): ?ValidationError`

Use `isValid()` for the fast path and `errors()` or `firstError()` for
diagnostics when validation fails.

## `ValidationError`

`ValidationError` is a compact diagnostic object.

Important methods:

- `message(): string`
- `line(): ?int`
- `column(): ?int`
- `level(): ?int`
- `__toString(): string`

`__toString()` is useful when you want a readable message in logs or exception
messages.

## Related

- [Overview](overview.md)
- [Builder](builder.md)
- [Import](import.md)
- [Validate XML against XSD](../validation/xsd.md)
