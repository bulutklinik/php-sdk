<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** Structured context attached to every {@see ApiException}. */
final class ApiErrorContext
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly ?int $resultType = null,
        public readonly string|int|null $errorType = null,
        public readonly mixed $data = null,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        public readonly ?int $retryAfter = null,
    ) {
    }
}
