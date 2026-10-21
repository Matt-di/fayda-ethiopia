# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.1.x | Yes |
| 1.0.x | Yes |
| < 1.0 | No |

Security fixes are made on the latest release line. Older supported lines may receive fixes when the issue applies to them.

## Reporting a vulnerability

Please do **not** open a public issue for a security vulnerability.

Use GitHub's private vulnerability reporting:

<https://github.com/Matt-di/fayda-ethiopia/security/advisories/new>

Include:

- the affected version or commit;
- a description of the issue and its impact;
- reproduction steps or a minimal proof of concept;
- any suggested fix or mitigation.

I will acknowledge the report as soon as possible and coordinate a fix and disclosure. Please allow time for a patched release before publishing details.

## Scope

Reports are especially useful for issues involving:

- authorization code, PKCE, `state`, or `nonce` handling;
- ID token, JWKS, `at_hash`, issuer, audience, or subject validation;
- signed or JSON userinfo handling;
- client assertion signing and private-key handling;
- Laravel callback, session, guard-login, or resolver behavior;
- token or private-key disclosure through logs, URLs, or exceptions.

## Deployment guidance

- Serve the redirect URI over HTTPS.
- Keep the private key outside the web root and never commit it.
- Register the exact redirect URI with Fayda.
- Keep PKCE enabled.
- Leave automatic guard login disabled unless your resolver explicitly maps a verified Fayda `sub` to a local user.
- Use a secure, `SameSite=None` session cookie if the provider uses `response_mode=form_post`.
- Store refresh tokens encrypted if you persist them.

## Out of scope

- Vulnerabilities in Fayda, eSignet, MOSIP, Laravel, Guzzle, or other dependencies. Report those to the upstream project.
- Misconfiguration of a host application, including missing HTTPS, incorrect redirect URIs, exposed private keys, or unsafe custom user resolvers.
- Social engineering, denial of service, or spam reports without a concrete code-level issue.
