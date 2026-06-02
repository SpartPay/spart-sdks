<?php

declare(strict_types=1);

namespace Spart\Sdk\Tests\Support;

use Spart\Sdk\Exceptions\SpartTransportException;
use Spart\Sdk\Http\HttpClient;
use Spart\Sdk\Http\HttpRequest;
use Spart\Sdk\Http\HttpResponse;

final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|\Throwable> */
    private array $script;

    private int $cursor = 0;

    /** @param list<HttpResponse|\Throwable> $script */
    public function __construct(array $script)
    {
        $this->script = $script;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if (!isset($this->script[$this->cursor])) {
            throw new SpartTransportException('FakeHttpClient: no scripted response for request #' . $this->cursor);
        }
        $next = $this->script[$this->cursor++];
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return $next;
    }
}
