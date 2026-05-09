<?php

declare(strict_types=1);

namespace CodeLockPro\Modules;

use CodeLockPro\CodeLockPro;

/**
 * Knowledge base module — module one of the server-side SDK.
 *
 * Each method maps to one of the unauthenticated public KB endpoints:
 *
 *   GET /v1/public/kb/{application_id}/articles
 *   GET /v1/public/kb/{application_id}/articles/{id_or_slug}
 *   GET /v1/public/kb/{application_id}/categories
 *   GET /v1/public/kb/{application_id}/search?q=…
 *
 * The application id is held by the parent client and injected into the
 * path here, so the developer's calling code stays terse:
 *
 *     $kb = new CodeLockPro('https://api.codelock.pro', '01H…');
 *     $articles = $kb->kb->getArticles();
 */
final class KnowledgeBase
{
    public function __construct(private readonly CodeLockPro $client)
    {
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
        return $this->client->request(
            'GET',
            $this->base() . '/search',
            ['q' => $query, 'limit' => $limit],
        );
    }

    private function base(): string
    {
        return '/v1/public/kb/' . $this->client->getApplicationId();
    }
}
