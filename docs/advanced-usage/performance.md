---
title: Performance
sidebar_position: 1
---

Imports stream. Rows are read from the file one at a time, validated and saved a chunk at a time, and failed rows are written to the report as they fail, so memory doesn't depend on the size of the file.

# The benchmark

`tests/Benchmark/LargeImportTest` imports 200,000 rows of products (SKU, Thaana name, price, stock, an enum, a boolean, a date and a brand lookup, with one row in a thousand failing) into SQLite with `ProductsImporter`, through Eloquent:

| Rows | Time | Peak memory |
| --- | --- | --- |
| 200,000 (CSV) | 38 s (5,300 rows/s) | +4.3 MB |

```bash
composer benchmark
IMPORTS_BENCHMARK_ROWS=1000000 IMPORTS_BENCHMARK_FORMAT=xlsx composer benchmark
```

It runs on every push, in its own CI job, and fails if memory grows past 64 MB.

# What makes it fast

- **One validator call per row, for the rules that need it.** Required and type checks (numbers, dates, booleans, enums) run directly; Laravel's validator only sees columns with rules of their own. A column with just `required()->number()` costs almost nothing.
- **Lookups per chunk.** `lookup()` columns run one `whereIn` per chunk and remember what they found.
- **Existing records per chunk.** `uniqueBy()` keys are loaded with one `whereIn` per chunk.
- **One transaction per chunk**, with a savepoint per row so a failing row rolls back alone.

# Tips

- Queue anything bigger than a few thousand rows (`queue()`, or the imports page). `import()` runs in the request.
- Prefer `lookup()` to `exists:` rules, and `uniqueBy()` to `unique:` rules: those run a query per row.
- Keep `prepareRow()`, `beforeSave()` and `afterSave()` free of per-row queries; load what a chunk needs in `beforePersist(array $rows)`.
- `chunk_size` trades memory and transaction length for fewer round trips. 500 suits most apps; 1000–2000 can be faster on a fast database.
- Models are saved with `save()`, so observers run for every row. If an observer is slow, make it queue its work.
- Queued imports read the file twice: once to count the rows (for progress), then to import them.
