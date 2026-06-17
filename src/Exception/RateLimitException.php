<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** 429 — throttled. The seconds-to-wait are available via `$e->context->retryAfter`. */
final class RateLimitException extends ApiException
{
}
