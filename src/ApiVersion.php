<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk;

/**
 * API version segment. The `/outher` surface is route-for-route identical on
 * both, so switching is configuration rather than a code change.
 */
enum ApiVersion: string
{
    case V3 = 'v3';
    case V4 = 'v4';
}
