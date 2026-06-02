<?php

declare(strict_types=1);

namespace Spart\Sdk\Http;

interface HttpClient
{
    /** @throws \Spart\Sdk\Exceptions\SpartTransportException */
    public function send(HttpRequest $request): HttpResponse;
}
