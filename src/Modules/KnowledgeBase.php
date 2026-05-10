<?php

declare(strict_types=1);

namespace CodeLockPro\Modules;

use CodeLockPro\CodeLockPro;
use CodeLockPro\Core\EventBus;
use CodeLockPro\Core\ModuleContext;

/**
 * Knowledge base — the built-in module of the server-side SDK.
 *
 * Conforms to the generic module contract: created by a factory that
 * receives a {@see ModuleContext}. The SDK core does not privilege KB
 * over any other module registered against the same contract.
 *
 * Each method maps to one of the unified KB endpoints on the upstream
 * CodeLockPro API. All endpoints require an OAuth bearer with the
 * appropriate scope (``kb:read`` for reads, ``kb:write`` for writes):
 *
 *   GET  /v1/kb/{application_id}/articles
 *   GET  /v1/kb/{application_id}/articles/{id_or_slug}
 *   GET  /v1/kb/{application_id}/categories
 *   GET  /v1/kb/{application_id}/search?q=…
 *   POST /v1/kb/{application_id}/articles/{id}/track-view
 *
 * Events emitted on the shared bus (subscribe via ``$client->on(...)``):
 *
 *   ``kb.article.viewed``    — payload ``['article_id' => …, 'slug' => …]``
 *   ``kb.search.performed``  — payload ``['query' => …, 'count' => …]``
 *
 *     $client = new CodeLockPro('https://api.codelock.pro', '01H…', bearerToken: $token);
 *     $articles = $client->kb()->getArticles();
 */
final class KnowledgeBase
{
    public function __construct(
        private readonly CodeLockPro $client,
        private readonly EventBus $bus,
        private readonly string $name,
    ) {
    }

    /** Module factory used by the SDK core (see {@see CodeLockPro::register()}). */
    public static function create(ModuleContext $ctx): self
    {
        return new self($ctx->client, $ctx->bus, $ctx->name);
    }

    /**
     * @param array{categoryId?: string, categorySlug?: string, limit?: int, startingAfter?: string} $options
     * @return array<string,mixed>
     */
    public function getArticles(array $options = []): array
    {
        return $this->client->request(
            'GET',
            $this->base() . '/articles',
            [
                'category_id'    => $options['categoryId']    ?? null,
                'category_slug'  => $options['categorySlug']  ?? null,
                'limit'          => $options['limit']         ?? null,
                'starting_after' => $options['startingAfter'] ?? null,
            ],
        );
    }

    /** @return array<string,mixed> */
    public function getArticle(string $idOrSlug): array
    {
        if ($idOrSlug === '') {
            throw new \InvalidArgumentException('getArticle: idOrSlug required');
        }
        return $this->client->request(
            'GET',
            $this->base() . '/articles/' . rawurlencode($idOrSlug),
        );
    }

    /** @return array<string,mixed> */
    public function getCategories(): array
    {
        return $this->client->request('GET', $this->base() . '/categories');
    }

    /** @return array<string,mixed> */
    public function search(string $query, ?int $limit = null): array
    {
        $result = $this->client->request(
            'GET',
            $this->base() . '/search',
            ['q' => $query, 'limit' => $limit],
        );
        $this->bus->emit($this->event('search.performed'), [
            'query' => $query,
            'count' => is_array($result['articles'] ?? null) ? count($result['articles']) : 0,
        ]);
        return $result;
    }

    /**
     * Action: emits ``kb.article.viewed`` on the shared event bus and
     * fires a best-effort POST to the upstream
     * ``/v1/kb/{app}/articles/{id}/track-view`` endpoint. The HTTP call
     * is wrapped in try/catch so view-tracking never throws — the bus
     * event always fires, even if the network request fails.
     */
    public function trackView(string $articleId, ?string $slug = null): void
    {
        if ($articleId === '') {
            throw new \InvalidArgumentException('trackView: articleId required');
        }
        $this->bus->emit($this->event('article.viewed'), [
            'article_id' => $articleId,
            'slug'       => $slug,
        ]);
        try {
            $this->client->request(
                'POST',
                $this->base() . '/articles/' . rawurlencode($articleId) . '/track-view',
            );
        } catch (\Throwable $e) {
            // Best-effort — see docblock.
        }
    }

    /** Subscribe to an event scoped to this module (``kb.<event>``). */
    public function on(string $event, callable $handler): callable
    {
        return $this->bus->on($this->event($event), $handler);
    }

    public function off(string $event, callable $handler): void
    {
        $this->bus->off($this->event($event), $handler);
    }

    private function base(): string
    {
        return '/v1/kb/' . $this->client->getApplicationId();
    }

    private function event(string $name): string
    {
        return $this->name . '.' . $name;
    }
}
