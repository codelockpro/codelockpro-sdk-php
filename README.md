# `codelockpro/sdk` (PHP)

Framework-agnostic, modular **server-side** SDK for CodeLockPro. Pure
library — no framework binding, no routing. The developer wires up
endpoints in their own stack (Laravel, Symfony, vanilla PHP) and uses this
client to call the upstream public CodeLockPro API.

## Installation

```bash
composer require codelockpro/sdk
```

## Usage

```php
use CodeLockPro\CodeLockPro;

$client = new CodeLockPro(
    'https://api.codelock.pro',
    '01HABCDEFGHJKMNPQRSTVWXYZ0', // application_id (ULID)
);

$articles   = $client->kb->getArticles();
$article    = $client->kb->getArticle('how-to-reset-password');
$categories = $client->kb->getCategories();
$hits       = $client->kb->search('billing');
```

## Architecture invariants

1. **Modular foundation.** Not knowledge-base specific. KB is module one;
   future modules attach to the same `CodeLockPro` instance.
2. **Server-only.** Designed to be called from the developer's backend
   process, not from a browser. The client SDK
   (`@codelockpro/sdk` on npm) is configured with the developer's own base
   URL and never talks directly to the CodeLockPro API.
3. **Zero web-framework dependencies.** Only `ext-curl` and `ext-json`.
