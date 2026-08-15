# Changelog

All notable changes to this project are documented in this file.
The version is derived from the git tag (see `docs/en/spec/specification.md` / `docs/ja/spec/specification.md`).

## 1.1.0 — 2026-08-15

[Compare v1.0.0...v1.1.0](https://github.com/ba0918/result/compare/v1.0.0...v1.1.0)

- **Added**: Pipe operator adapter API in the `ba0918\Result\Pipe` namespace
  (`map` / `mapErr` / `andThen` / `orElse` / `inspect` / `inspectErr`) for PHP 8.5's
  `|>` operator. Each function takes the business callable and returns a
  `Closure(Result): Result` that delegates to the same-named Result method, so the
  short-circuit semantics of the method chain are preserved. The adapters also
  work without `|>` on PHP 8.3/8.4 by calling the returned closure directly.
  The closure's input type is bound to the callable's parameter type, so piping a
  mismatched `Result` into a stage is a static error.
- **Added**: PHP 8.5 CI coverage. `|>` integration tests, the PHPUnit suite
  (`composer test:php85`) and PHPStan analysis with the PHP 8.5 grammar
  (`composer phpstan:php85`) run on the PHP 8.5 job.
- **Changed**: `Result` and `Option` type parameters are now declared covariant
  (`@template-covariant`). Statically, a `Result` with a narrower value/error
  type can now be passed where a wider one is expected (e.g. `Result<never, E>`
  as `Result<T, E>`). Runtime behavior is unchanged.
