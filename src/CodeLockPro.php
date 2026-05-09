<?php

declare(strict_types=1);

namespace CodeLockPro;

use CodeLockPro\Modules\KnowledgeBase;

/**
 * `codelockpro/sdk` — framework-agnostic server-side SDK.
 *
 * Architecture invariants (mirrors the JS package — see /docs/sdk/README.md):
 *
 *   1. Modular foundation. Not knowledge-base specific. KB is module one;
 *      future modules (community, chatbot, …) attach to the same instance.
 *   2. Pure library. No framework binding, no routing. The developer wires
 *      up endpoints in their own stack (Laravel, Symfony, vanilla PHP) and
 *      uses this client to call the upstream CodeLockPro public API.
 *   3. Zero web-framework dependencies. Only ext-curl + ext-json.
 *
 * Configure the client with the upstream CodeLockPro base URL — typically
 * `https://api.codelock.pro`. The corresponding `application_id` is set
 * once and used by every module call.
 */
final class CodeLockPro
{
    public readonly KnowledgeBase $kb;

    /**
     * @param string $baseUrl       Upstream CodeLockPro base URL, e.g. https://api.codelock.pro
     * @param string $applicationId The ULID of the developer's application — scopes every read
     * @param array<string,string> $defaultHeaders Optional headers merged into every request.
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $applicationId,
        private readonly array $defaultHeaders = [],
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('CodeLockPro: baseUrl is required');
        }
        if ($applicationId === '') {
            throw new \InvalidArgumentException('CodeLockPro: applicationId is required');
        }
        $this->kb = new KnowledgeBase($this);
    }

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    /**
     * Internal HTTP helper. Modules call this rather than curl directly so
     * cross-cutting concerns (auth, telemetry) stay in one place.
     *
     * @param array<string,scalar|null> $query
     * @return array<string,mixed>
     * @throws CodeLockProApiException on a non-2xx response.
     */
    public function request(string $method, string $path, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $filtered = array_filter(
                $query,
                static fn ($v) => $v !== null && $v !== ''
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
        foreach ($this->defaultHeaders as $name => $value) {
            $headers[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

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
