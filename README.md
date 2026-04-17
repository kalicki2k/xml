# kalle/xml

`kalle/xml` is a strict, builder-first XML writer for modern PHP.

It focuses on a small, predictable API for building XML documents with correct
escaping, namespace-aware names, and deterministic output.

## Why kalle/xml

- immutable document and node objects
- explicit namespace-aware names via `Xml::qname()`
- deterministic serialization for tests and reproducible builds
- early XML 1.0 validation for names and character data
- compact and pretty-printed output from the same model
- string output and file output through the same serializer

## Installation

```bash
composer require kalle/xml
```

See `examples/` for runnable scripts.

## Quick Start

Build a document and call `toString()`:

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;

echo Xml::document(
    Xml::element('catalog')
        ->child(
            Xml::element('book')
                ->attribute('isbn', '9780132350884')
                ->text('Clean Code'),
        ),
)->toString();
```

Output:

```xml
<?xml version="1.0" encoding="UTF-8"?><catalog><book isbn="9780132350884">Clean Code</book></catalog>
```

## Namespace-Aware API

Use `Xml::qname()` for namespace-aware element and attribute names. Raw
`prefix:name` strings are rejected on purpose.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element(Xml::qname('feed', 'urn:feed'))
        ->declareDefaultNamespace('urn:feed')
        ->declareNamespace('media', 'urn:media')
        ->child(
            Xml::element(Xml::qname('entry', 'urn:feed'))
                ->child(Xml::element(Xml::qname('thumbnail', 'urn:media', 'media'))),
        ),
)->withoutDeclaration();

echo $document->toString(WriterConfig::pretty(emitDeclaration: false));
```

Output:

```xml
<feed xmlns="urn:feed" xmlns:media="urn:media">
    <entry>
        <media:thumbnail/>
    </entry>
</feed>
```

Namespace rules:

- default namespaces apply to elements, not attributes
- use `declareDefaultNamespace()` for explicit default namespace declarations
- use `declareNamespace()` for explicit prefixed declarations
- required namespaces are declared automatically when missing from scope
- namespace declarations are serialized before normal attributes

## Writer Model

Escaping happens during serialization, not while building the object graph.
The model stores raw values, validates them early, and renders them
deterministically.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;

$document = Xml::document(
    Xml::element('payload')
        ->comment('raw script fragment follows')
        ->child(
            Xml::element('script')
                ->child(Xml::cdata('if (a < b && c > d) { return "ok"; }')),
        )
        ->processingInstruction('render-cache', 'ttl="300"')
        ->child(Xml::element('status')->text('ok')),
)->withoutDeclaration();

echo $document->toString(WriterConfig::pretty(emitDeclaration: false));
```

Output:

```xml
<payload>
    <!--raw script fragment follows-->
    <script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script>
    <?render-cache ttl="300"?>
    <status>ok</status>
</payload>
```

## Validation and Errors

The library rejects invalid XML early:

- invalid XML names
- invalid XML 1.0 characters
- invalid comment and processing-instruction content
- namespace declaration conflicts
- unsupported declaration settings such as non-UTF-8 encodings

Exception messages are intentionally short and aimed at the call site, so
invalid builder input is easy to diagnose.

## Key Repository Directories

```text
src/
  Attribute/    Attribute value object
  Builder/      Entry-point helpers such as Xml::document()
  Document/     XmlDocument and XmlDeclaration
  Escape/       Escaping and character validation
  Exception/    Library-specific exception types
  Name/         QualifiedName value object
  Namespace/    Namespace declarations and scope handling
  Node/         Element and other writer node types
  Validate/     XML name validation
  Writer/       Serializer and writer configuration
tests/
  Unit/         Focused object and validation tests
  Integration/  Serializer, file output, and parser-backed checks
examples/       Runnable examples such as catalog.php and namespaced-feed.php
benchmarks/     Reserved for future benchmark fixtures
docs/           Maintainer-facing notes
```

## Development

Common development commands:

```bash
composer test
composer stan
composer cs-check
composer cs-fix
composer qa
```

The integration suite uses `DOMDocument` to verify that serialized XML is
well-formed, not just string snapshots.

## Scope

Included today:

- XML documents and declarations
- elements, text, comments, CDATA, and processing instructions
- namespace-aware names and namespace declarations
- deterministic compact and pretty-printed serialization
- file output with library-specific write exceptions

Not included:

- parsing
- XPath
- XSD validation
- streaming parser APIs

## Status

The project is intentionally narrow in scope for its first public release. The
priority is API clarity, XML correctness, and a solid foundation for the writer
API.
