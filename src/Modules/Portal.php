<?php

declare(strict_types=1);

namespace CodeLockPro\Modules;

use CodeLockPro\Core\ModuleContext;

/**
 * Canonical portal forum module for the server-side SDK.
 *
 * This class is a compatibility wrapper over the existing community
 * implementation and intentionally keeps the same method surface.
 */
final class Portal
{
    public function __construct(
        private readonly Community $community,
    ) {
    }

    /** Module factory used by the SDK core (see {@see \CodeLockPro\CodeLockPro::register()}). */
    public static function create(ModuleContext $ctx): self
    {
        return new self(Community::create($ctx));
    }

    /**
     * @param array{skip?: int, limit?: int} $options
     * @return array<string,mixed>
     */
    public function getThreads(array $options = []): array
    {
        return $this->community->getThreads($options);
    }

    /**
     * @param array{skip?: int, limit?: int} $options
     * @return array<string,mixed>
     */
    public function getPosts(string $threadId, array $options = []): array
    {
        return $this->community->getPosts($threadId, $options);
    }

    /**
     * @param array{title?: string, body?: string} $input
     * @return array<string,mixed>
     */
    public function createThread(array $input): array
    {
        return $this->community->createThread($input);
    }

    /**
     * @param array{body?: string} $input
     * @return array<string,mixed>
     */
    public function createPost(string $threadId, array $input): array
    {
        return $this->community->createPost($threadId, $input);
    }

    /**
     * @param array{reason?: string} $input
     * @return array<string,mixed>
     */
    public function flagThread(string $threadId, array $input = []): array
    {
        return $this->community->flagThread($threadId, $input);
    }

    /**
     * @param array{reason?: string} $input
     * @return array<string,mixed>
     */
    public function flagPost(string $postId, array $input = []): array
    {
        return $this->community->flagPost($postId, $input);
    }

    /**
     * @param callable(mixed): void $handler
     * @return callable Unsubscribe callback.
     */
    public function on(string $event, callable $handler): callable
    {
        return $this->community->on($event, $handler);
    }

    /**
     * @param callable(mixed): void $handler
     */
    public function off(string $event, callable $handler): void
    {
        $this->community->off($event, $handler);
    }
}
