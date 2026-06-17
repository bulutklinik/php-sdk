<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** 422, or an envelope with a string errorType of "validation". */
final class ValidationException extends ApiException
{
}
