<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/**
 * 403 — the token authenticated but is not permitted. Either it lacks the
 * `apiouther` scope or it resolves to a user with no company. The company
 * boundary comes from the token, never from request input, so retrying with
 * different body parameters will not help.
 */
final class AuthorizationException extends ApiException
{
}
