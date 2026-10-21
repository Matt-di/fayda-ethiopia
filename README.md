# Fayda Ethiopia for PHP & Laravel

[![PHP](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Fayda (eSignet / MOSIP) OpenID Connect integration with a framework-agnostic core and a first-class Laravel bridge.

It implements the relying-party flow used by VeriFayda 2.0:

- Authorization Code flow with **PKCE (S256)**
- **`private_key_jwt`** client authentication (RS256)
- **JWKS signature verification** of the `id_token` and signed `userinfo`
- Strict JWT validation (`iss`, `aud`/`azp`, `exp`, `iat`, `nbf`, `nonce`, and `at_hash` when present)
- Typed `FaydaUser`, `TokenResponse`, and `LoginResult` objects
- Optional Laravel routes, events, custom user resolver, and guard login

Works in plain PHP, Laravel 11/12, and any PSR-18 compatible project.

---

## Requirements

- PHP 8.2 or newer
- `ext-json`, `ext-openssl`
- Composer
- A Fayda/eSignet client ID and RSA private key
- A registered redirect URI

No database table or user model is required.

Laravel is optional. The core package does not require any Laravel component.

> **Laravel 11 is end-of-life.** It still works with this package and is covered by a compatibility job, but Composer 2.9+ may block its installation because of known framework security advisories. Use Laravel 12 (or newer) for production applications.

---

## Installation

```bash
composer require mattdi/fayda-ethiopia
```

Laravel auto-discovers `Mattdi\Fayda\Laravel\FaydaServiceProvider` and the `Fayda` facade.

---

## Laravel setup

### 1. Publish the config

```bash
php artisan vendor:publish --tag=fayda-config
```

### 2. Configure `.env`

Only these values are required:

```dotenv
FAYDA_ISSUER=https://esignet.sandbox.example
FAYDA_CLIENT_ID=your-client-id
FAYDA_PRIVATE_JWK_BASE64=your-base64-encoded-private-jwk
FAYDA_REDIRECT_URI=https://your-app.test/auth/fayda/callback

# Optional, when your JWKS publishes a kid:
# FAYDA_KEY_ID=your-key-id
```

Everything else has a sensible default. Endpoints are discovered from `FAYDA_ISSUER`; scopes, ACR values, claims, PKCE, routes, and login can be overridden only when you need to. See the [configuration reference](#configuration-reference).

`FAYDA_PRIVATE_JWK_BASE64` is the usual Fayda onboarding format. If your key is stored differently, provide **one** of these instead:

1. `FAYDA_PRIVATE_KEY` (raw PEM)
2. `FAYDA_PRIVATE_KEY_PATH` (PEM file)
3. `FAYDA_PRIVATE_JWK_BASE64` (base64 encoded JWK JSON)
4. `FAYDA_PRIVATE_JWK` (JWK JSON or array)

`FAYDA_KEY_ID` is optional, but include it when your JWKS publishes a `kid`.

If endpoints are omitted, the client fetches `{issuer}/.well-known/openid-configuration` and caches it. Explicit endpoints always win over discovery.

### 3. Request the claims you need

The default claims request asks for the common profile fields. Override it with a JSON value:

```dotenv
FAYDA_CLAIMS='{"userinfo":{"name":{"essential":true},"phone_number":{"essential":true},"email":{"essential":true},"picture":{"essential":false},"gender":{"essential":false},"birthdate":{"essential":false},"address":{"essential":false}},"id_token":{}}'
```

Only request ACR values your relying party has been approved for. `mosip:idp:acr:biometrics` typically requires additional onboarding.

### 4. Add a login link

The package registers these routes by default:

| Method | Path | Name |
|---|---|---|
| `GET` | `/auth/fayda/redirect` | `fayda.redirect` |
| `GET`/`POST` | `/auth/fayda/callback` | `fayda.callback` |

```blade
<a href="{{ route('fayda.redirect') }}">Sign in with Fayda</a>
```

The callback route is excluded from CSRF protection because eSignet may deliver it as a cross-site `form_post`. The one-time `state` value protects the callback.

If you use `form_post`, Laravel’s default `SameSite=Lax` session cookie is **not** sent on cross-site POST requests. Set `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true`, or use `response_mode=query` when the provider allows it. Otherwise the callback will fail with an invalid-state error.

Disable the built-in routes with `FAYDA_ROUTES=false` and use the client directly.

### 5. Handle the callback

**Option A — let the package log the user in:**

```dotenv
FAYDA_AUTH_LOGIN=true
FAYDA_AUTH_GUARD=web
FAYDA_REDIRECT_AFTER_LOGIN=/
```

Listen to the success event to finish provisioning:

```php
use Mattdi\Fayda\Laravel\Events\FaydaLoginSucceeded;

Event::listen(FaydaLoginSucceeded::class, function (FaydaLoginSucceeded $event) {
    $event->user();               // Mattdi\Fayda\Core\Dto\FaydaUser
    $event->result->token;        // TokenResponse
    $event->authenticatable;      // Authenticatable|null
});
```

A `FaydaLoginFailed` event is dispatched with the thrown `FaydaException` when the callback fails.

**Option B — handle everything yourself:**

Disable the routes and call the client:

```php
use Illuminate\Http\Request;
use Mattdi\Fayda\Laravel\Facades\Fayda;

Route::get('/auth/fayda/callback', function (Request $request) {
    $result = Fayda::authenticate(
        code: (string) $request->input('code', ''),
        state: $request->input('state'),
        error: $request->input('error'),
        errorDescription: $request->input('error_description'),
        iss: $request->input('iss'),
    );

    $user = $result->user;   // verified Fayda identity
    $token = $result->token; // access_token, id_token, refresh_token

    // Map/create your application user, then:
    auth()->login($appUser);

    return redirect()->intended('/');
});
```

`Fayda::handleCallback(...)` is a shortcut that returns only the `FaydaUser`.

### 6. Custom user mapping

Implement `Mattdi\Fayda\Laravel\Auth\FaydaUserResolver`:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Mattdi\Fayda\Core\Dto\FaydaUser;
use Mattdi\Fayda\Laravel\Auth\FaydaUserResolver;

final class AppFaydaResolver implements FaydaUserResolver
{
    public function resolve(FaydaUser $user): ?Authenticatable
    {
        if ($user->sub === null) {
            return null;
        }

        // `sub` is the stable Fayda identifier. Add a `fayda_sub` column to
        // your users table (or use your own identity-link table).
        return User::query()->firstOrCreate(
            ['fayda_sub' => $user->sub],
            [
                'name' => $user->name,
                'email' => $user->emailVerified === true ? $user->email : null,
                'password' => bcrypt(str()->random(40)),
            ],
        );
    }
}
```

```dotenv
FAYDA_USER_RESOLVER=App\Services\AppFaydaResolver
```

Use `sub` as the stable key. Only link an existing account by email when `emailVerified === true`; trusting an unverified email can allow account takeover.

The package does **not** create users, tables, or migrations. Your resolver owns persistence and registration rules. You can also skip the resolver entirely and handle the verified `FaydaUser` from the callback yourself.

---

## Plain PHP usage

The core is framework-agnostic. It uses PSR-18 for HTTP, PSR-17 for request factories, PSR-16 for caching, and a `StateStore` contract for the authorization state.

```php
<?php

require 'vendor/autoload.php';

use Mattdi\Fayda\FaydaClient;

session_start();

$fayda = FaydaClient::fromArray([
    'issuer' => 'https://esignet.sandbox.example',
    'client' => [
        'id' => 'your-client-id',
        'private_jwk_base64' => getenv('FAYDA_PRIVATE_JWK_BASE64'),
        'key_id' => 'your-key-id',
    ],
    'redirect_uri' => 'https://your-app.test/fayda/callback',
    'scopes' => ['openid', 'profile', 'email'],
    'acr_values' => ['mosip:idp:acr:generated-code'],
    'pkce' => true,
]);

// login.php
header('Location: '.$fayda->authorizationUrl());
exit;

// callback.php
$result = $fayda->authenticate(
    code: $_GET['code'] ?? '',
    state: $_GET['state'] ?? null,
    iss: $_GET['iss'] ?? null,
);

$user = $result->user;

echo $user->name;
echo $user->phoneNumber;
echo $user->email;
```

The default `NativeSessionStateStore` uses `$_SESSION`. Start the session before any output, or pass your own `StateStore` implementation to the `FaydaClient` constructor:

```php
use Mattdi\Fayda\Core\Contracts\StateStore;

final class RedisStateStore implements StateStore
{
    public function put(string $state, array $payload, int $ttlSeconds): void { /* ... */ }
    public function pull(string $state): ?array { /* ... */ }
}
```

For dependency injection or a custom PSR-18 client:

```php
use GuzzleHttp\Client;
use Mattdi\Fayda\FaydaClient;

$fayda = new FaydaClient(
    config: $config,
    http: new Client(['timeout' => 10]),
    cache: $psr16Cache,
    states: $stateStore,
);
```

---

## Configuration reference

At minimum, set `FAYDA_ISSUER`, `FAYDA_CLIENT_ID`, one private-key source, and `FAYDA_REDIRECT_URI`. Every other key below is optional.

<details>
<summary>Show all configuration keys</summary>

| Key | Env | Default | Description |
|---|---|---|---|
| `issuer` | `FAYDA_ISSUER` | `null` | OIDC issuer, used for discovery and `iss` validation |
| `discovery.enabled` | `FAYDA_DISCOVERY` | `true` | Fetch `.well-known/openid-configuration` when endpoints are missing |
| `endpoints.authorization` | `FAYDA_AUTHORIZATION_ENDPOINT` | `null` | Authorization endpoint |
| `endpoints.token` | `FAYDA_TOKEN_ENDPOINT` | `null` | Token endpoint (also the client assertion audience) |
| `endpoints.userinfo` | `FAYDA_USERINFO_ENDPOINT` | `null` | Userinfo endpoint |
| `endpoints.jwks` | `FAYDA_JWKS_URI` | `null` | JWKS endpoint |
| `client.id` | `FAYDA_CLIENT_ID` | `null` | OIDC client ID |
| `client.assertion_ttl` | `FAYDA_ASSERTION_TTL` | `300` | Client assertion lifetime, capped at 300 seconds |
| `client.private_key` | `FAYDA_PRIVATE_KEY` | `null` | Raw PEM private key |
| `client.private_key_path` | `FAYDA_PRIVATE_KEY_PATH` | `null` | PEM file path |
| `client.private_jwk_base64` | `FAYDA_PRIVATE_JWK_BASE64` | `null` | Base64 encoded private JWK |
| `client.private_jwk` | `FAYDA_PRIVATE_JWK` | `null` | Private JWK JSON/array |
| `client.key_id` | `FAYDA_KEY_ID` | `null` | `kid` header for the client assertion |
| `redirect_uri` | `FAYDA_REDIRECT_URI` | `null` | Registered callback URL |
| `scopes` | `FAYDA_SCOPES` | `openid profile email` | Space-separated scopes |
| `acr_values` | `FAYDA_ACR_VALUES` | generated-code + linked-wallet | Requested authentication contexts |
| `claims` | `FAYDA_CLAIMS` | profile claims | OIDC claims request JSON |
| `ui_locales` | `FAYDA_UI_LOCALES` | `en` | UI locale |
| `display` | `FAYDA_DISPLAY` | `page` | `page`, `popup`, `touch`, `wap` |
| `response_mode` | `FAYDA_RESPONSE_MODE` | `null` | `query`, `fragment`, or `form_post` |
| `pkce` | `FAYDA_PKCE` | `true` | Enable PKCE S256 |
| `state_ttl` | `FAYDA_STATE_TTL` | `600` | Authorization state lifetime, in seconds |
| `require_id_token` | `FAYDA_REQUIRE_ID_TOKEN` | `true` | Fail when the token response has no `id_token` |
| `fetch_userinfo` | `FAYDA_FETCH_USERINFO` | `true` | Fetch userinfo after the token exchange |
| `clock_skew` | `FAYDA_CLOCK_SKEW` | `60` | JWT clock skew, in seconds |
| `jwks_cache_ttl` | `FAYDA_JWKS_CACHE_TTL` | `3600` | JWKS cache lifetime |
| `cache_store` | `FAYDA_CACHE_STORE` | `null` | Laravel cache store for discovery/JWKS |
| `routes.enabled` | `FAYDA_ROUTES` | `true` | Register the Laravel routes |
| `routes.prefix` | `FAYDA_ROUTE_PREFIX` | `auth/fayda` | Route prefix |
| `auth.login` | `FAYDA_AUTH_LOGIN` | `false` | Log the resolved user into the guard |
| `auth.guard` | `FAYDA_AUTH_GUARD` | `web` | Guard used for login |
| `auth.redirect_after_login` | `FAYDA_REDIRECT_AFTER_LOGIN` | `/` | Fallback redirect |
| `user_resolver` | `FAYDA_USER_RESOLVER` | `null` | Custom `FaydaUserResolver` class |

</details>

---

## Security

This package deliberately fixes the gaps common in sample integrations:

- **JWKS verification** for every JWT, including signed `userinfo` responses. Decoding a token is not verification.
- **`kid` rotation** support: an unknown key triggers one cache refresh, then fails closed.
- **Nonce validation** in the `id_token`, tied to the stored authorization request.
- **One-time, session-bound `state`** with a TTL and constant-time comparison.
- **PKCE S256** with a cryptographically random verifier stored server-side.
- **Short-lived client assertions** (maximum 5 minutes) with `jti`, `iss`, `sub`, `aud`, `iat`, and `exp`.
- **Audience/authorized-party checks** (`aud` and `azp`).
- **Mandatory `exp`, `iat`, and `iss` claims** on ID tokens and signed userinfo.
- **Access-token binding** through `at_hash` when the provider includes it.
- **Issuer and subject binding** across the callback `iss`, ID token, and userinfo `sub`.
- **Session regeneration** on login.
- **No tokens in URLs** and no token/claim logging by default.

Recommendations:

- Always use HTTPS for the redirect URI.
- Keep the private key outside the web root and never commit it.
- Register the exact redirect URI with Fayda.
- Leave `FAYDA_PKCE=true` even when using `private_key_jwt`.
- Only request ACR values and claims your relying party is approved for.
- Store refresh tokens encrypted if you persist them.

---

## Sandbox checklist

1. Register `https://your-app.test/auth/fayda/callback` (exact scheme, host, path, and port).
2. Set the sandbox client ID and private JWK.
3. Set the issuer, or all four endpoints explicitly.
4. Start the flow at `/auth/fayda/redirect`.
5. Complete consent in the same browser session.
6. Check that the callback returns a verified `FaydaUser`.
7. If the sandbox uses `application/jwt` userinfo, no extra configuration is needed.

Common errors:

| Error | Likely cause |
|---|---|
| `ConfigurationException` | Missing client ID, redirect URI, endpoint, or private key |
| `InvalidStateException` | Expired/replayed state, different browser/session, or missing session cookie |
| `InvalidTokenException` | Wrong issuer/audience, nonce mismatch, clock skew, or rotated JWKS |
| `TokenRequestException` | Invalid code/redirect URI, or client assertion rejected |
| `UserInfoException` | Access token rejected or userinfo endpoint unavailable |

---

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

The unit suite uses a fixture RSA key and a fake PSR-18 client. The Laravel suite uses Orchestra Testbench. No sandbox credentials are required for tests.

---

## License

MIT. See [LICENSE](LICENSE).
