# Roadmap

## v1.1 Status

`kalle/xml` v1.1 is now a two-path writer package:

- immutable document model for tree-based XML construction
- `StreamingXmlWriter` for incremental XML writing

The v1.1 milestone adds:

- stateful streaming XML writing with explicit start/end operations
- string, file-path, and PHP stream targets through the same writer foundation
- namespace-aware streaming for default and prefixed namespaces
- consistency tests between document-model output and streaming output
- maintained examples and benchmark fixtures for real writer workflows

## Current Direction

The package remains intentionally writer-focused. Near-term work should improve
writer ergonomics, correctness, performance visibility, and documentation
quality without expanding into unrelated XML features.

## Out Of Scope

The roadmap still excludes:

- parsing
- XPath
- XSD validation
- reader/query APIs
- general-purpose XML tooling outside writing
