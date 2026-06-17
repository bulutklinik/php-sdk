<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Exception;

/** Network failure, timeout, DNS or TLS error — no usable HTTP response. */
final class TransportException extends BulutklinikException
{
}
