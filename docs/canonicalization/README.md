# Canonicalize XML

Use `XmlCanonicalizer` when XML needs one deterministic, canonical string
representation across writer, reader, import, and DOM interop flows.

## What This Is For

Use this capability when you want to:

- keep snapshot tests stable
- compare XML across different package flows
- hash or deduplicate normalized XML
- canonicalize one selected subtree instead of a full document

## API Shape

The canonicalization surface stays intentionally small:

- `XmlCanonicalizer::document()` for writer-built `XmlDocument`
- `XmlCanonicalizer::readerDocument()` for loaded `ReaderDocument`
- `XmlCanonicalizer::readerElement()` for loaded or queried `ReaderElement` subtrees
- `XmlCanonicalizer::domDocument()` for native `DOMDocument`
- `XmlCanonicalizer::xmlString()` for raw XML strings
- `CanonicalizationOptions` for the one supported option: including comments

The implementation uses inclusive XML canonicalization through native DOM
canonicalization facilities. Comments are excluded by default.

## Canonicalize One Writer-Built Document

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Canonicalization\XmlCanonicalizer;

$document = XmlBuilder::document(
    XmlBuilder::element('catalog')
        ->child(
            XmlBuilder::element('book')
                ->attribute('b', '2')
                ->attribute('a', '1')
                ->text('Clean Code'),
        ),
)->withoutDeclaration();

echo XmlCanonicalizer::document($document) . "\n";
```

Canonical output never includes an XML declaration.

## Canonicalize Across Different Package Flows

One practical workflow is to build XML, load it back through the reader path,
and confirm both flows normalize to the same canonical output.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Canonicalization\XmlCanonicalizer;
use Kalle\Xml\Reader\XmlReader;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;

$serialized = XmlWriter::toString(
    $document,
    WriterConfig::compact(emitDeclaration: false),
);

$readerDocument = XmlReader::fromString($serialized);

if (XmlCanonicalizer::document($document) !== XmlCanonicalizer::readerDocument($readerDocument)) {
    throw new RuntimeException('Canonical XML output diverged across package flows.');
}
```

## Canonicalize One Queried Subtree

`readerElement()` keeps subtree canonicalization separate from the streaming
cursor and separate from DOM wrapper APIs. The selected subtree is normalized
as its own canonical subtree instead of depending on ancestor formatting
whitespace or ancestor namespace declaration order.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Canonicalization\XmlCanonicalizer;

$entry = $readerDocument->findFirst('/feed:feed/feed:entry', ['feed' => 'urn:feed']);

if ($entry !== null) {
    echo XmlCanonicalizer::readerElement($entry) . "\n";
}
```

## Include Comments Only When Needed

Comments are excluded by default. Opt in explicitly when the workflow really
needs them.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Canonicalization\CanonicalizationOptions;
use Kalle\Xml\Canonicalization\XmlCanonicalizer;

echo XmlCanonicalizer::xmlString(
    '<!--before--><catalog><!--inside--><book/></catalog>',
    CanonicalizationOptions::withComments(),
) . "\n";
```

## Boundaries

`XmlCanonicalizer` stays intentionally narrow:

- it canonicalizes existing XML inputs; it does not mutate them
- it does not add XML diff, patch, merge, or signature tooling
- it does not add exclusive canonicalization variants or XPath node-set configuration
- it does not try to reinterpret or strip text nodes beyond standard canonical XML rules

## Related

- [Getting Started](../getting-started.md)
- [DOM interop guide](../dom/interop.md)
- [Import guides](../import/README.md)
- [Canonicalization API](../api/canonicalization.md)
- Example: [canonicalize-feed.php](../../examples/canonicalize-feed.php)
