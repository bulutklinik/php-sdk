<?php

declare(strict_types=1);

namespace Bulutklinik\Sdk\Tests;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** A PSR-18 client that records requests and delegates responses to a handler. */
final class MockClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var callable(RequestInterface): ResponseInterface */
    private $handler;

    /** @param callable(RequestInterface): ResponseInterface $handler */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return ($this->handler)($request);
    }
}
