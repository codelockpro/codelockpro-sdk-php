<?php

declare(strict_types=1);

namespace CodeLockPro;

use CodeLockPro\Core\EventBus;
use CodeLockPro\Core\ModuleContext;
use CodeLockPro\Core\ModuleRegistry;
use CodeLockPro\Modules\Community;
use CodeLockPro\Modules\KnowledgeBase;

/**
 * Server-side foundation of the CodeLockPro SDK.
 *
 * Handles all communication with the CodeLockPro API from the server.
 * Exposes a modular registration surface for composing CodeLockPro
 * features into any PHP stack without framework binding or routing.
 *
 * Modules are attached via {@see CodeLockPro::register()} and looked
 * up by name via {@see CodeLockPro::module()}. Each module receives a
 * {@see ModuleContext} carrying the shared event bus and a
 * back-reference to this client for HTTP.
 *
 * The SDK requires an OAuth client credential — pass the bearer token
 * via the ``$bearerToken`` constructor argument and it will be added
 * as ``Authorization: Bearer <token>`` to every upstream request. The
 * scopes the token must carry depend on which modules are used (e.g.
 * ``kb:read`` and / or ``kb:write`` for the knowledge-base module).
 *
 * Architecture invariants (mirrors the JS package — see /docs/sdk/README.md):
 *
 *   1. Modular foundation. No coupling to any specific module.
 *   2. Pure library. No framework binding, no routing. The developer
 *      wires up endpoints in their own stack.
 *   3. Zero web-framework dependencies. Only ext-curl + ext-json.
 */
final class CodeLockPro
{
    private readonly EventBus $bus;
    private readonly ModuleRegistry $registry;

    /**
     * @param string $baseUrl       Upstream CodeLockPro base URL
     * @param string $applicationId The ULID of the developer's application — scopes every read
     * @param array<string,string> $defaultHeaders Optional headers merged into every request.
     * @param array<string,callable>|false $modules Module factories to register at construction.
     *        Each value is ``callable(ModuleContext): object``. Default: KB only. Pass ``false``
     *        to skip the default registration and register everything explicitly.
     * @param ?string $bearerToken OAuth bearer token. If non-null and non-empty, sent as
     *        ``Authorization: Bearer <token>`` on every upstream request.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $applicationId,
        private readonly array $defaultHeaders = [],
        array|false $modules = null,
        private readonly ?string $bearerToken = null,
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('CodeLockPro: baseUrl is required');
        }
        if ($applicationId === '') {
            throw new \InvalidArgumentException('CodeLockPro: applicationId is required');
        }
        $this->bus = new EventBus();
        $this->registry = new ModuleRegistry();

        // Default registration: KB is module one. Pass ``modules: false``
        // (or override with an explicit array) to opt out — the core
        // itself has no knowledge-base coupling.
        if ($modules === false) {
            $toRegister = [];
        } elseif ($modules === null) {
            $toRegister = ['kb' => [KnowledgeBase::class, 'create']];
        } else {
            $toRegister = $modules;
        }
        foreach ($toRegister as $name => $factory) {
            $this->register((string) $name, $factory);
        }
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    /**
     * Register a module after construction. KB, future first-party
     * modules, and host-app extensions all use this entry point.
     *
     * @param callable(ModuleContext): object $factory
     */
    public function register(string $name, callable $factory): object
    {
        $ctx = new ModuleContext(name: $name, client: $this, bus: $this->bus);
        $instance = $factory($ctx);
        if (!is_object($instance)) {
            throw new \LogicException("register: factory for \"$name\" must return an object");
        }
        return $this->registry->register($name, $instance);
    }

    /** Look up a previously registered module by name. */
    public function module(string $name): object
    {
        return $this->registry->get($name);
    }

    /** @return list<string> */
    public function moduleNames(): array
    {
        return $this->registry->names();
    }

    public function on(string $event, callable $handler): callable
    {
        return $this->bus->on($event, $handler);
    }

    public function off(string $event, callable $handler): void
    {
        $this->bus->off($event, $handler);
    }

    public function emit(string $event, mixed $payload = null): void
    {
        $this->bus->emit($event, $payload);
    }

    /**
     * Convenience accessor for the knowledge-base module — equivalent to
     * ``$client->module('kb')``. Provided as ergonomic sugar so existing
     * call sites read naturally; KB is not privileged inside the core.
     */
    public function kb(): KnowledgeBase
    {
        /** @var KnowledgeBase $kb */
        $kb = $this->module('kb');
        return $kb;
    }

    /**
     * Convenience accessor for the community module — equivalent to
     * `$client->module('community')`.
     */
    public function community(): Community
    {
        /** @var Community $community */
        $community = $this->module('community');
        return $community;
    }

    /**
     * Internal HTTP helper. Modules call this rather than curl directly so
     * cross-cutting concerns (auth, telemetry) stay in one place.
     *
     * @param array<string,scalar|null> $query
     * @param ?array<string,mixed> $jsonBody
     * @return array<string,mixed>
     * @throws CodeLockProApiException on a non-2xx response.
     */
    public function request(string $method, string $path, array $query = [], ?array $jsonBody = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $filtered = array_filter(
                $query,
                static fn($v) => $v !== null && $v !== ''
            );
            if ($filtered !== []) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('CodeLockPro: failed to initialise curl');
        }

        $headers = ['Accept: application/json'];
        if ($this->bearerToken !== null && $this->bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
        }
        foreach ($this->defaultHeaders as $name => $value) {
            $headers[] = "$name: $value";
        }
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $options = [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody);
            if ($encoded === false) {
                throw new \RuntimeException(
                    'CodeLockPro: failed to encode JSON request body for '
                    . strtoupper($method)
                    . ' '
                    . $path
                    . ': '
                    . json_last_error_msg()
                );
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("CodeLockPro: HTTP transport error: $err");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 204) {
            return [];
        }
        if ($status < 200 || $status >= 300) {
            throw new CodeLockProApiException($status, (string) $body);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('CodeLockPro: non-JSON response body');
        }
        return $decoded;
    }
}
