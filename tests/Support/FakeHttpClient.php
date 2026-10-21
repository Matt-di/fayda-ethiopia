<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    public array $queue;

    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<ResponseInterface|\Throwable>  $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \RuntimeException('No fake HTTP response is queued.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
