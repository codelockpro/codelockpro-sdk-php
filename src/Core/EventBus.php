<?php

declare(strict_types=1);

namespace CodeLockPro\Core;

/**
 * Tiny synchronous event bus shared by every SDK module.
 *
 * Mirrors the JS package: minimal API (``on`` / ``off`` / ``emit``), no
 * priority, no async fan-out, no wildcards. Modules namespace their
 * events as ``"<module>.<event>"`` (``kb.article.viewed``,
 * ``checkout.session.created``, …). The bus has no awareness of which
 * module emits what.
 */
final class EventBus
{
    /** @var array<string, list<callable>> */
    private array $handlers = [];

    /**
     * Subscribe to an event. Returns the registered callable so callers
     * can pass it to :meth:`off`.
     */
    public function on(string $event, callable $handler): callable
    {
        if ($event === '') {
            throw new \InvalidArgumentException('on: event name is required');
        }
        $this->handlers[$event] ??= [];
        $this->handlers[$event][] = $handler;
        return $handler;
    }

    public function off(string $event, callable $handler): void
    {
        if (!isset($this->handlers[$event])) {
            return;
        }
        $this->handlers[$event] = array_values(array_filter(
            $this->handlers[$event],
            static fn ($h) => $h !== $handler,
        ));
        if ($this->handlers[$event] === []) {
            unset($this->handlers[$event]);
        }
    }

    public function emit(string $event, mixed $payload = null): void
    {
        if (!isset($this->handlers[$event])) {
            return;
        }
        // Snapshot so handlers may unsubscribe themselves safely.
        foreach ($this->handlers[$event] as $handler) {
            try {
                $handler($payload);
            } catch (\Throwable $e) {
                // Never let one handler break another. Surface to PHP's
                // error log rather than swallowing silently.
                error_log(
                    sprintf(
                        'CodeLockPro: handler for "%s" threw: %s',
                        $event,
                        $e->getMessage(),
                    ),
                );
            }
        }
    }
}
