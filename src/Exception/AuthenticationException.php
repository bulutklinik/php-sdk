<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** 401, a logout (resultType 2), or a failed token refresh. */
final class AuthenticationException extends ApiException
{
}
