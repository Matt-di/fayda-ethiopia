# Fayda Ethiopia for PHP & Laravel

[![PHP](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-11%20%7C%2012-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Fayda (eSignet / MOSIP) OpenID Connect integration with a framework-agnostic core and a first-class Laravel bridge.

It implements the relying-party flow used by VeriFayda 2.0:

- Authorization Code flow with **PKCE (S256)**
- **`private_key_jwt`** client authentication (RS256)
- **JWKS signature verification** of the `id_token` and signed `userinfo`
- `iss`, `aud`/`azp`, `exp`, `iat`, `nbf`, `nonce`, and `state` validation
- Typed `FaydaUser`, `TokenResponse`, and `LoginResult` objects
- Optional Laravel routes, events, user resolver, guard login, and Eloquent persistence

Works in plain PHP, Laravel 11/12, and any PSR-18 compatible project.

---

## Requirements

- PHP 8.2 or newer
- `ext-json`, `ext-openssl`
- Composer
- A Fayda/eSignet client ID and RSA private key
- A registered redirect URI

Laravel is optional. The core package does not require any Laravel component.

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

```dotenv
FAYDA_ENV=sandbox

# Issuer + discovery (recommended), or set every endpoint explicitly
FAYDA_ISSUER=https://esignet.sandbox.example
FAYDA_AUTHORIZATION_ENDPOINT=
FAYDA_TOKEN_ENDPOINT=
FAYDA_USERINFO_ENDPOINT=
FAYDA_JWKS_URI=

# Client credentials (private_key_jwt)
FAYDA_CLIENT_ID=your-client-id
FAYDA_CLIENT_ASSERTION_TYPE=urn:ietf:params:oauth:client-assertion-type:jwt-bearer

# Provide ONE private key source.
# Base64 encoded private JWK (Fayda onboarding format):
FAYDA_PRIVATE_JWK_BASE64=
# Or a PEM file:
# FAYDA_PRIVATE_KEY_PATH=/secure/fayda-private.pem
# Or a raw PEM string:
# FAYDA_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----..."
# Or JWK JSON:
# FAYDA_PRIVATE_JWK='{"kty":"RSA",...}'
FAYDA_KEY_ID=your-key-id

FAYDA_REDIRECT_URI=https://your-app.test/auth/fayda/callback
FAYDA_SCOPES="openid profile email"
FAYDA_ACR_VALUES="mosip:idp:acr:generated-code mosip:idp:acr:linked-wallet"
FAYDA_UI_LOCALES=en
FAYDA_PKCE=true

# Laravel bridge
FAYDA_ROUTES=true
FAYDA_AUTH_LOGIN=false
FAYDA_PERSISTENCE=false
```

Private key sources are checked in this order:

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
        // Find or create your user. Return null to skip login.
        return User::query()->firstOrCreate(
            ['email' => $user->email],
            ['name' => $user->name, 'password' => bcrypt(str()->random(40))],
        );
    }
}
```

```dotenv
FAYDA_USER_RESOLVER=App\Services\AppFaydaResolver
```

### 7. Optional Eloquent persistence

```dotenv
FAYDA_PERSISTENCE=true
FAYDA_CREATE_USERS=true
FAYDA_USER_MODEL=App\Models\User
```

```bash
php artisan vendor:publish --tag=fayda-migrations
php artisan migrate
```

Add the trait to your user model if you want the relationship:

```php
use Mattdi\Fayda\Laravel\HasFaydaIdentity;

final class User extends Authenticatable
{
    use HasFaydaIdentity;
}
```

The default resolver:

- always resolves an existing `fayda_identities` row by `subject` + `client_id`
- links an existing user by **verified** email when available
- creates a user only when `FAYDA_CREATE_USERS=true`
- returns `null` otherwise, so your application can handle registration/approval

The `fayda_identities.user_id` column is a `unsignedBigInteger`. Adjust the published migration if your user model uses UUIDs.

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
$result = $fayda->authenticate($_GET['code'] ?? '', $_GET['state'] ?? null);

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
| `persistence.enabled` | `FAYDA_PERSISTENCE` | `false` | Enable the Eloquent resolver |
| `persistence.user_model` | `FAYDA_USER_MODEL` | `App\Models\User` | User model class |
| `persistence.table` | `FAYDA_IDENTITIES_TABLE` | `fayda_identities` | Identity table |
| `persistence.create_users` | `FAYDA_CREATE_USERS` | `false` | Create users automatically |
| `user_resolver` | `FAYDA_USER_RESOLVER` | `null` | Custom `FaydaUserResolver` class |

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
