<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** An HTTP response was received but the call was not successful. */
class ApiException extends BulutklinikException
{
    public function __construct(string $message, public readonly ApiErrorContext $context)
    {
        parent::__construct($message);
    }

    /**
     * Map an API failure to the most specific exception type.
     * Precedence: logout (resultType 2) -> string errorType "validation" -> HTTP status.
     */
    public static function fromContext(ApiErrorContext $context, string $message): self
    {
        if ($context->resultType === 2) {
            return new AuthenticationException($message, $context);
        }

        $type = \is_string($context->errorType) ? \strtolower($context->errorType) : null;
        if ($type === 'validation' || $context->httpStatus === 422) {
            return new ValidationException($message, $context);
        }

        return match ($context->httpStatus) {
            401 => new AuthenticationException($message, $context),
            403 => new AuthorizationException($message, $context),
            404 => new NotFoundException($message, $context),
            429 => new RateLimitException($message, $context),
            default => new self($message, $context),
        };
    }
}
