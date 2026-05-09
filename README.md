# `codelockpro/sdk`

Pure server-side, modular PHP SDK — the CodeLockPro client framework
on the server. The knowledge base is module one; future modules
(community, checkout, payments, chatbot, …) plug into the same
`CodeLockPro` instance through the same registration surface.

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

Mapped to the unauthenticated public REST surface:

```
GET /v1/public/kb/{application_id}/articles
GET /v1/public/kb/{application_id}/articles/{id_or_slug}
GET /v1/public/kb/{application_id}/categories
GET /v1/public/kb/{application_id}/search?q=…
```

Each call returns the raw decoded JSON body as an associative array.
On a non-2xx response the client throws
`CodeLockPro\CodeLockProApiException` carrying the HTTP status and
response body.
