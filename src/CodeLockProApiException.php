<?php

declare(strict_types=1);

namespace CodeLockPro;

/**
 * Raised when the upstream CodeLockPro API returns a non-2xx response.
 * Callers should catch this to differentiate transport-level failures
 * (which surface as `\RuntimeException`) from upstream-rejected requests.
 */
final class CodeLockProApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
        parent::__construct("CodeLockPro API $statusCode: $body");
    }
}
