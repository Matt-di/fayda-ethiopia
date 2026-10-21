# Changelog

All notable changes to `mattdi/fayda-ethiopia` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-21

### Added

- Laravel 13 support.

### Changed

- Dropped Laravel 11 support (end-of-life).
- Support matrix is now Laravel 12 on PHP 8.2–8.5 and Laravel 13 on PHP 8.3–8.5.
- Updated development dependencies to Testbench 10/11 and Pest 3/4.
- CI now audits production dependencies and runs on pinned Ubuntu 24.04 runners.

## [1.0.0] - 2026-10-21

### Added

- Framework-agnostic Fayda/eSignet OpenID Connect client.
- Authorization Code flow with PKCE S256, state, and nonce.
- `private_key_jwt` client authentication with RSA JWK or PEM keys.
- JWKS discovery, caching, key rotation, and signature verification.
- ID token validation (`iss`, `aud`/`azp`, `exp`, `iat`, `nbf`, `nonce`, and `at_hash`).
- Callback issuer (RFC 9207) and userinfo subject binding.
- JSON and signed-JWT userinfo support.
- Typed DTOs: `FaydaUser`, `Address`, `TokenResponse`, `LoginResult`.
- Laravel service provider, facade, routes, controller, and events.
- Optional guard login and custom user resolver.
- Publishable config.
- Unit and Laravel feature test suites, PHPStan, Pint, and CI.
