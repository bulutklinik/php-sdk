<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** 401, a revoked token (resultType 2), or an expired one (resultType 4). */
final class AuthenticationException extends ApiException
{
}
