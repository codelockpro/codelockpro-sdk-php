# `codelockpro/sdk`

Pure server-side, modular PHP SDK — the CodeLockPro client framework
on the server. Ships with the knowledge base as a built-in module and
includes portal/community module factories for forum operations;
additional modules plug into the same `CodeLockPro` instance through
the same registration surface.

## Install

```sh
composer require codelockpro/sdk
```

> **Source repository.** This package is published to Packagist from a
> read-only subtree mirror at
> [`mbos01/codelockpro-sdk-php`](https://github.com/mbos01/codelockpro-sdk-php).
> The canonical source lives in
> [`mbos01/codelockpro`](https://github.com/mbos01/codelockpro) under
> `sdk/php/` — open issues and PRs there.

> **License.** The SDK is proprietary and may only be used in
> combination with an active CodeLockPro account. See
> [`LICENSE`](./LICENSE).

## Architecture invariants

1. **Modular foundation.** The core has no per-module coupling.
2. **Pure library.** No framework binding (Laravel, Symfony, vanilla
   PHP — the developer wires the routes).
3. **Zero web-framework dependencies.** Only `ext-curl` and `ext-json`.

## Usage

```php
use CodeLockPro\CodeLockPro;

$client = new CodeLockPro(
    baseUrl: 'https://api.codelock.pro',
    applicationId: '01H…',
);

// Data
$articles = $client->kb()->getArticles();
$article  = $client->kb()->getArticle('how-to-reset-password');

// Actions (server-side: emits the bus event)
$client->kb()->trackView($article['id'], $article['slug']);

// Events
$client->on('kb.article.viewed', function (array $payload): void {
    error_log("viewed {$payload['article_id']}");
});
$client->on('kb.search.performed', function (array $payload): void {
    error_log("{$payload['count']} results for {$payload['query']}");
});
```

`$client->kb()` is a convenience accessor for `$client->module('kb')`.
The core has no KB-specific code path.

## Registering more modules

```php
use CodeLockPro\CodeLockPro;
use CodeLockPro\Core\ModuleContext;

$client = new CodeLockPro(
    baseUrl: 'https://api.codelock.pro',
    applicationId: '01H…',
    modules: false,  // opt out of the default KB registration
);

$client->register('kb',       [\CodeLockPro\Modules\KnowledgeBase::class, 'create']);
$client->register('checkout', function (ModuleContext $ctx) {
    return new class($ctx) {
        public function __construct(public ModuleContext $ctx) {}
        public function start(string $productId): array {
            $session = $this->ctx->client->request(
                'POST', "/v1/checkout/sessions", ['product_id' => $productId],
            );
            $this->ctx->bus->emit("{$this->ctx->name}.session.created", [
                'product_id' => $productId,
            ]);
            return $session;
        }
    };
});

$checkout = $client->module('checkout');
$session  = $checkout->start('pro-monthly');
```

## Module author contract

A module factory is any `callable(ModuleContext): object`. The context
exposes:

- `$ctx->name`   — the registered name (`"kb"`, `"checkout"`, …)
- `$ctx->client` — back-reference to `CodeLockPro` (use `request()` for HTTP)
- `$ctx->bus`    — shared event bus (`on` / `off` / `emit`)

Modules namespace their events as `<ctx->name>.<event>` so listeners
stay unambiguous when many modules are registered.

## Knowledge base — module one

Mapped to the unified, secured KB REST surface (every call requires an
OAuth bearer carrying `kb:read` for reads and `kb:write` for writes;
view tracking is `kb:read`):

```
GET  /v1/kb/{application_id}/articles
GET  /v1/kb/{application_id}/articles/{id_or_slug}
GET  /v1/kb/{application_id}/categories
GET  /v1/kb/{application_id}/search?q=…
POST /v1/kb/{application_id}/articles/{id}/track-view
```

Each call returns the raw decoded JSON body as an associative array.
On a non-2xx response the client throws
`CodeLockPro\CodeLockProApiException` carrying the HTTP status and
response body.

## Portal module (canonical)

Register the canonical portal module via the same module contract:

```php
$client->register('portal', [\CodeLockPro\Modules\Portal::class, 'create']);

$threads = $client->portal()->getThreads(['limit' => 20]);
$post    = $client->portal()->createPost('thread_123', ['body' => 'I hit this too']);
```

## Community module (backward-compatible alias)

Register the community module via the same module contract:

```php
$client->register('community', [\CodeLockPro\Modules\Community::class, 'create']);

$threads = $client->community()->getThreads(['limit' => 20]);
$post    = $client->community()->createPost('thread_123', ['body' => 'I hit this too']);
```

Mapped to the upstream community/forum API surface:

```
GET  /v1/portal/forum/threads
GET  /v1/portal/forum/threads/{threadId}/posts
POST /v1/portal/forum/threads
POST /v1/portal/forum/threads/{threadId}/posts
POST /v1/portal/forum/threads/{threadId}/flag
POST /v1/portal/forum/posts/{postId}/flag
```

Events emitted on the shared bus are namespaced with the registered
module name:

- `community.thread.created`
- `community.post.created`
- `community.thread.flagged`
- `community.post.flagged`

When registered as `portal`, event names are emitted as:

- `portal.thread.created`
- `portal.post.created`
- `portal.thread.flagged`
- `portal.post.flagged`

## Migration (community → portal)

Legacy community naming is preserved for backward compatibility. New PHP
integrations should use portal naming.

| Legacy | Canonical |
| --- | --- |
| `CodeLockPro\Modules\Community` | `CodeLockPro\Modules\Portal` |
| `$client->community()` | `$client->portal()` |
| `$client->register('community', ...)` | `$client->register('portal', ...)` |

Deprecation policy and timeline:

- `community` naming remains fully supported in the current `0.x` line.
- `portal` naming is the canonical path for all new integrations.
- Any future removal of `community` naming will happen only in a future
  major release, with migration notice published in advance.
