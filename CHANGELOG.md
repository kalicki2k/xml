# Changelog

All notable changes to `kalle/xml` will be documented in this file.

## 0.1.0 - 2026-04-19

Initial public release.

- immutable tree-based XML construction with `XmlBuilder`
- whole-document serialization with `XmlWriter`
- incremental XML output with `StreamingXmlWriter`
- streaming XML input with `StreamingXmlReader`, including subtree extraction and non-overlapping record iteration via `readElements()`
- read-only XML loading, traversal, and small namespace-aware queries with `XmlReader`, `ReaderDocument`, and `ReaderElement`
- explicit DOM interop with `XmlDomBridge` and the DOM entry points on `XmlReader`
- reader-to-writer import with `XmlImporter`
- compact XSD validation with `XmlValidator`
