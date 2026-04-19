# Import API

The import API bridges reader-side values back into the writer-side model.

## `XmlImporter`

`XmlImporter` is a static bridge with two public entry points:

- `document(ReaderDocument $document): XmlDocument`
  Imports a full reader-side document into a regular writer-side document.
- `element(ReaderElement $element): Element`
  Imports one reader-side element subtree into a regular writer-side element.

Behavior notes:

- Imported results are normal `XmlDocument` and `Element` values.
- They work with `XmlBuilder`, `XmlWriter`, `StreamingXmlWriter`, and `XmlValidator`.
- Element names, attributes, text, comments, CDATA, processing instructions, and root-level namespace declarations are preserved across the bridge.

Small example:

```php
$writerElement = XmlImporter::element($entry)->attribute('exported', true);
```

Common exception:

- `ImportException` when the source document or subtree uses unsupported constructs

Unsupported cases include:

- document-level comments
- document-level processing instructions
- DOCTYPE declarations
- entity references

## Related

- [Overview](overview.md)
- [Reader](reader.md)
- [Builder](builder.md)
- [Validation](validation.md)
- [Import Reader Results](../import/importing.md)
