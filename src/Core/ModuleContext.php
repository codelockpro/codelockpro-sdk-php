<?php

declare(strict_types=1);

namespace CodeLockPro\Core;

use CodeLockPro\CodeLockPro;

/**
 * Context handed to every module factory at registration time.
 *
 * Provides the module's registered name, the shared {@link EventBus},
 * and a typed reference to the parent {@link CodeLockPro} client (so
 * modules can call ``$client->request(...)`` for HTTP). The context
 * is intentionally minimal so additional modules plug in without
 * core changes.
 */
final class ModuleContext
{
    public function __construct(
        public readonly string $name,
        public readonly CodeLockPro $client,
        public readonly EventBus $bus,
    ) {
    }
}
