# DOM Interop API

The DOM interop API keeps native DOM integration separate from the writer,
reader, import, and validation surfaces.

## `XmlDomBridge`

`XmlDomBridge` exports writer-side values into native DOM documents.
It stays separate from `Xml`, `XmlReader`, `XmlImporter`, and `XmlValidator`
so DOM interop remains one small bridge instead of a wider wrapper layer.

Important methods:

- `toDomDocument(XmlDocument $document): DOMDocument`
  Exports a full writer-side document into `DOMDocument`.
- `elementToDomDocument(Element $element): DOMDocument`
  Exports one writer-side element subtree into a one-root `DOMDocument`.

Behavior notes:

- Namespace declarations are resolved using the same rules as the regular writer path.
- Comments, CDATA, text nodes, processing instructions, and mixed element content are preserved.
- Meaningful declaration settings on `XmlDocument` are written into `DOMDocument` properties such as version, encoding, and standalone.
- `elementToDomDocument()` gives you a document-rooted DOM representation without adding a mutable wrapper API.
- If a caller needs the exported root node directly, use `$domDocument->documentElement` on the returned document.

Common exception:

- `DomInteropException` when native DOM creation fails

## DOM Entry Points on `XmlReader`

`XmlReader` is still the reader entry point, even when the input already exists
as DOM.

Important methods:

- `fromDomDocument(DOMDocument $document): ReaderDocument`
- `fromDomElement(DOMElement $element): ReaderElement`

Behavior notes:

- These methods wrap the provided DOM values directly instead of reparsing XML text.
- Once loaded, traversal, queries, and import use the same `ReaderDocument` and `ReaderElement` types as string, file, or stream input.
- `fromDomDocument()` requires a document element.

Common exception:

- `DomInteropException` when a `DOMDocument` cannot be entered into the reader flow

## Related

- [Overview](overview.md)
- [Reader](reader.md)
- [Import](import.md)
- [Use DOM Interop](../dom/interop.md)
