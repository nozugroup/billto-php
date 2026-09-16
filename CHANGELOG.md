# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package adheres to
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-16

First stable release, and the baseline every later version builds on.

### Added

- Coverage of the whole BillTo API v1: invoices (CRUD, issue, KSeF, PDF/XML, e-mail, public link,
  payments), orders (pay-then-invoice, advances, corrections), contractors, products, price groups,
  incoming (cost) invoices, warehouse, reports and reference data.
- PSR-18 / PSR-17 transport with an automatic `Idempotency-Key` on mutations, and retries with
  backoff for 429 (honouring `Retry-After`), 502/503/504 and network errors.
- Typed exceptions per HTTP status (`ValidationException`, `RateLimitException`, ...).
- Lazy pagination: `->all()` returns a `Paginator`.
- An integration-identifying `User-Agent`, so the API can tell which client a request came from and
  reach its authors before breaking changes.
- Optional Laravel service provider and facade.
