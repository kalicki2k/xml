# Work with Namespaces

Namespace handling is shared across the writer, reader, query, and import
surfaces. The package keeps that model explicit.

## What This Is For

Use the namespace-aware parts of `kalle/xml` when element or attribute names
need to carry a namespace URI instead of relying on raw lexical names alone.

## When To Use It

Use this guide when your XML includes default namespaces, prefixed names, or
queries that need explicit namespace aliases.

## Write Namespaced XML

Use `Xml::qname()` for namespace-aware element and attribute names.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Builder\Xml;

$feed = Xml::element(Xml::qname('feed', 'urn:feed'))
    ->declareDefaultNamespace('urn:feed')
    ->declareNamespace('media', 'urn:media')
    ->child(
        Xml::element(Xml::qname('entry', 'urn:feed'))
            ->child(
                Xml::element(Xml::qname('thumbnail', 'urn:media', 'media')),
            ),
    );
```

- Use `Xml::qname()` for namespace-aware element and attribute names.
- Use `declareDefaultNamespace()` for an explicit default namespace declaration.
- Use `declareNamespace()` for explicit prefixed declarations.
- Default namespaces apply to elements, not attributes.
- Namespaced attributes require an explicit prefix.

Raw `prefix:name` strings are rejected on purpose.

## Read Namespaced XML

Use `QualifiedName` when a reader lookup should match a specific namespace URI.

```php
<?php

declare(strict_types=1);

use Kalle\Xml\Name\QualifiedName;

$entryName = new QualifiedName('entry', 'urn:feed');
$hrefName = new QualifiedName('href', 'urn:xlink', 'xlink');
```

`ReaderElement::namespacesInScope()` exposes namespace declarations separately
from regular attributes.

## Query Namespaced XML

- XPath does not apply the XML default namespace automatically.
- Map the default namespace to an explicit alias such as `['feed' => 'urn:feed']`.
- Prefixed namespaces already in scope on the query context are registered automatically.

Given a loaded `ReaderDocument` in `$document`:

```php
$entry = $document->findFirst('/feed:feed/feed:entry', [
    'feed' => 'urn:feed',
]);
```

## Import Namespaced XML

`XmlImporter` preserves namespace-aware names and rebuilds root-level namespace
declarations from the imported subtree so the result stays correct when written
again.

## Boundaries

The namespace support is explicit by design:

- default namespaces never apply to attributes
- query expressions still follow XPath namespace rules
- namespace-aware names should use `Xml::qname()` or `QualifiedName`, not raw prefixed strings
- import preserves namespace structure, but it does not widen the reader or writer APIs into a broader XML framework

## Related

- [Writer guides](../writer/README.md)
- [Reader guides](../reader/README.md)
- [Import guides](../import/README.md)
- [Getting Started](../getting-started.md)
- [API reference](../api/README.md)
