# Canonicalization API

The canonicalization API provides deterministic XML normalization for the
existing writer, reader, import, and DOM interop flows.

## `XmlCanonicalizer`

`XmlCanonicalizer` is the static entry point for canonical XML output.

Important methods:

- `document(XmlDocument $document, ?CanonicalizationOptions $options = null): string`
- `readerDocument(ReaderDocument $document, ?CanonicalizationOptions $options = null): string`
- `readerElement(ReaderElement $element, ?CanonicalizationOptions $options = null): string`
- `domDocument(DOMDocument $document, ?CanonicalizationOptions $options = null): string`
- `xmlString(string $xml, ?CanonicalizationOptions $options = null): string`

Behavior notes:

- Canonical output is generated through native DOM canonicalization.
- The API stays on inclusive canonical XML only; it does not expose exclusive mode or node-set configuration.
- Canonical output never includes an XML declaration.
- `document()` canonicalizes writer-built `XmlDocument` values.
- `readerDocument()` canonicalizes the existing DOM-backed reader document directly.
- `readerElement()` canonicalizes one selected subtree as a standalone canonical subtree through the existing import and DOM bridge flows.
- `domDocument()` requires a `DOMDocument` with a document element.
- `xmlString()` parses through the existing reader flow, so malformed XML raises `ParseException`.

## `CanonicalizationOptions`

`CanonicalizationOptions` keeps the canonicalization surface small.

Constructors and named constructors:

- `__construct(bool $includeComments = false)`
- `withoutComments(): self`
- `withComments(): self`

Important methods:

- `includeComments(): bool`
- `withIncludeComments(bool $includeComments): self`

Behavior notes:

- comments are excluded by default
- `withComments()` is the only built-in variant
- no whitespace-rewriting, exclusive-mode, or signature-oriented options are exposed

## Common exception

- `CanonicalizationException`
  Canonicalization failed or a required DOM input shape was missing.

## Related

- [Overview](overview.md)
- [Reader](reader.md)
- [DOM interop](dom.md)
- [Canonicalize XML](../canonicalization/README.md)
