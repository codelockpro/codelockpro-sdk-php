<?php

declare(strict_types=1);

namespace CodeLockPro\Modules;

use CodeLockPro\CodeLockPro;
use CodeLockPro\Core\EventBus;
use CodeLockPro\Core\ModuleContext;

/**
 * Community forum module for the server-side SDK.
 *
 * Mirrors the JS community surface with data accessors, actions, and
 * event helpers.
 */
final class Community
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
     * @param array{skip?: int, limit?: int} $options
     * @return array<string,mixed>
     */
    public function getThreads(array $options = []): array
    {
        return $this->client->request(
            'GET',
            $this->base() . '/threads',
            [
                'skip' => $options['skip'] ?? null,
                'limit' => $options['limit'] ?? null,
            ],
        );
    }

    /**
     * @param array{skip?: int, limit?: int} $options
     * @return array<string,mixed>
     */
    public function getPosts(string $threadId, array $options = []): array
    {
        if ($threadId === '') {
            throw new \InvalidArgumentException('getPosts: threadId required');
        }
        return $this->client->request(
            'GET',
            $this->base() . '/threads/' . rawurlencode($threadId) . '/posts',
            [
                'skip' => $options['skip'] ?? null,
                'limit' => $options['limit'] ?? null,
            ],
        );
    }

    /**
     * @param array{title?: string, body?: string} $input
     * @return array<string,mixed>
     */
    public function createThread(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('createThread: title required');
        }
        if ($body === '') {
            throw new \InvalidArgumentException('createThread: body required');
        }

        $thread = $this->client->request(
            'POST',
            $this->base() . '/threads',
            [],
            ['title' => $title, 'body' => $body],
        );
        $this->bus->emit($this->event('thread.created'), ['thread' => $thread]);
        return $thread;
    }

    /**
     * @param array{body?: string} $input
     * @return array<string,mixed>
     */
    public function createPost(string $threadId, array $input): array
    {
        if ($threadId === '') {
            throw new \InvalidArgumentException('createPost: threadId required');
        }
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('createPost: body required');
        }

        $post = $this->client->request(
            'POST',
            $this->base() . '/threads/' . rawurlencode($threadId) . '/posts',
            [],
            ['body' => $body],
        );
        $this->bus->emit($this->event('post.created'), ['post' => $post]);
        return $post;
    }

    /**
     * @param array{reason?: string} $input
     * @return array<string,mixed>
     */
    public function flagThread(string $threadId, array $input = []): array
    {
        if ($threadId === '') {
            throw new \InvalidArgumentException('flagThread: threadId required');
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        $thread = $this->client->request(
            'POST',
            $this->base() . '/threads/' . rawurlencode($threadId) . '/flag',
            [],
            ['reason' => $reason !== '' ? $reason : null],
        );
        $this->bus->emit($this->event('thread.flagged'), ['thread' => $thread]);
        return $thread;
    }

    /**
     * @param array{reason?: string} $input
     * @return array<string,mixed>
     */
    public function flagPost(string $postId, array $input = []): array
    {
        if ($postId === '') {
            throw new \InvalidArgumentException('flagPost: postId required');
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        $post = $this->client->request(
            'POST',
            $this->base() . '/posts/' . rawurlencode($postId) . '/flag',
            [],
            ['reason' => $reason !== '' ? $reason : null],
        );
        $this->bus->emit($this->event('post.flagged'), ['post' => $post]);
        return $post;
    }

    /**
     * Subscribe to an event scoped to this module (`<module>.<event>`).
     *
     * @param callable(mixed): void $handler
     * @return callable Unsubscribe callback.
     */
    public function on(string $event, callable $handler): callable
    {
        return $this->bus->on($this->event($event), $handler);
    }

    /**
     * Unsubscribe a previously-registered handler for a scoped module event.
     *
     * @param callable(mixed): void $handler
     */
    public function off(string $event, callable $handler): void
    {
        $this->bus->off($this->event($event), $handler);
    }

    private function base(): string
    {
        return '/v1/portal/forum';
    }

    private function event(string $name): string
    {
        return $this->name . '.' . $name;
    }
}
