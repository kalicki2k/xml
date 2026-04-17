# Documentation

This directory contains maintainer-facing notes that do not belong in the
public README or the package runtime.

Recommended content here:

- roadmap and milestone status notes
- release and QA checklists
- release notes and release process details
- architecture notes for document output and namespace behavior
- compatibility decisions and XML edge-case references
- contributor-oriented guidance when the workflow grows beyond the main README

Repository layout highlights:

- `src/` contains the library code grouped by concern
- `src/Namespace/` contains namespace declarations and namespace scope handling
- `src/Reader/` contains `XmlReader` plus the read-only traversal and query support
- `tests/Unit/` covers focused object behavior and validation
- `tests/Integration/` covers document/streaming output, reader traversal, reader queries, file output, and parser-backed checks
- `examples/` contains runnable example scripts
- `benchmarks/` contains maintained performance comparison fixtures
- `docs/roadmap.md` tracks the current package direction and milestone status
- `docs/release-process.md` captures the maintainer release workflow

Keep public usage guidance in the top-level `README.md`. Use this directory for
deeper notes that help maintainers evolve the library safely.
