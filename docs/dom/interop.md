# Use DOM Interop

Use DOM interop when `kalle/xml` needs to cross into native DOM-based code
without changing the package into a mutable DOM wrapper framework.

## What This Is For

Use this capability when you want to:

- export a writer-side `XmlDocument` into `DOMDocument`
- export a writer-side `Element` into `DOMDocument`
- enter the reader flow from `DOMDocument` or `DOMElement`
- run writer -> DOM -> reader/query/import/write workflows

## When To Use It

Choose DOM interop when surrounding code, framework hooks, or third-party APIs
already use native DOM types and you want to keep the rest of the XML workflow
inside `kalle/xml`.

## Export Writer Values into DOM

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Dom\XmlDomBridge;

$document = Xml::document(
    Xml::element('catalog')
        ->child(Xml::element('book')->attribute('isbn', '9780132350884')),
);

$domDocument = XmlDomBridge::toDomDocument($document);
```

`XmlDomBridge::toDomDocument()` exports `XmlDocument` into `DOMDocument`.
`XmlDomBridge::elementToDomDocument()` exports one `Element` subtree into a
one-root `DOMDocument`. When you need the exported root node directly, use
`$domDocument->documentElement`.

## Enter the Reader Flow from DOM

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Reader\XmlReader;

$readerDocument = XmlReader::fromDomDocument($domDocument);
$book = $readerDocument->rootElement()->firstChildElement('book');
```

Use `XmlReader::fromDomDocument()` for a full document and
`XmlReader::fromDomElement()` for a subtree entry point.

## Practical Roundtrip

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;

$domDocument = XmlDomBridge::toDomDocument(
    Xml::document(
        Xml::element(Xml::qname('feed', 'urn:feed'))
            ->declareDefaultNamespace('urn:feed')
            ->child(
                Xml::element(Xml::qname('entry', 'urn:feed'))
                    ->attribute('sku', 'item-1002')
                    ->child(Xml::element(Xml::qname('title', 'urn:feed'))->text('Notebook set')),
            ),
    ),
);

$entry = XmlReader::fromDomDocument($domDocument)->findFirst('/feed:feed/feed:entry[@sku="item-1002"]', [
    'feed' => 'urn:feed',
]);

if ($entry !== null) {
    echo Xml::document(
        XmlImporter::element($entry)->attribute('exported', true),
    )->withoutDeclaration()->toString() . "\n";
}
```

## Boundaries

The DOM interop stays intentionally small:

- it exports into native DOM documents; it does not wrap DOM behind a new mutable API
- loading from DOM enters the existing reader model; it does not add a broad DOM facade
- reader-to-writer conversion still belongs to `XmlImporter`
- schema validation still belongs to `XmlValidator`

## Related

- [Writer guides](../writer/README.md)
- [Reader guides](../reader/README.md)
- [Import guides](../import/README.md)
- [API reference](../api/README.md)
- Examples: [dom-roundtrip.php](../../examples/dom-roundtrip.php), [dom-feed-query.php](../../examples/dom-feed-query.php), [dom-invoice-stream.php](../../examples/dom-invoice-stream.php)
