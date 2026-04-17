# Benchmarks

This directory is intentionally kept lightweight until the project has real
benchmark fixtures worth maintaining.

When benchmarking is added, keep it focused on release decisions:

- serializer throughput for compact and pretty modes
- namespace-heavy documents
- large mixed-content documents
- regression comparisons between tagged releases

Do not commit generated reports or one-off profiling artifacts here.
