# Changelog

## 2.0.0 (unreleased)

### Breaking changes

- Require PHP 7.2.5+ (PHP 7 or 8) and Guzzle 7.15.2+. The previous dependency
  range included Guzzle 6 and older PHP releases. Current Composer advisory
  checks cannot resolve that legacy combination without exceptions.
- Require `overtrue/http` 1.2.3+, the first 1.x release containing both the
  PHP 8 collection compatibility and resource-upload corrections used here.
- Endpoint paths beginning with a single slash now resolve under the configured
  API base path, matching the documented `/mail/send` usage. Callers that need an
  origin-root path must supply an absolute URL.

- Redirect following is disabled by default. Explicitly enabled redirects must
  remain on the initial scheme, host, and port; cross-origin/downgrade redirects
  throw before a second request is sent.

- Prebuilt multipart bodies and low-level HTTP-client multipart calls are rejected;
  use the SDK `upload()` or `request()` multipart option instead.

### Fixed

- Use HTTPS by default and honor an explicitly configured base URI.
- Add POST authentication to URL-encoded form bodies or multipart fields.
- Preserve existing query parameters and form values, including duplicate and
  encoded values; use correctly encoded authentication values.
- Remove the obsolete PSR-7 `modify_request()` dependency.
- Retain response casting, API-level failure responses, transport/HTTP exceptions,
  and asynchronous behavior.

### Tests

- Add mocked outgoing-request regressions, syntax checks, dependency audits, and
  lowest/latest dependency CI for PHP 7.2–7.4 and 8.0–8.5.
