<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** 401 after a failed or impossible refresh, or a revoked session (resultType 2). */
final class AuthenticationException extends ApiException
{
}
